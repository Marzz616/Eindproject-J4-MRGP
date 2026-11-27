<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use App\Exceptions\HttpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Settings;
use App\Exceptions\ValidationException;
use App\Models\Categorie;
use App\Models\Product;
use DateTimeImmutable;

/**
 * Producten en voorraad — US-VRD-01 t/m US-VRD-12, US-RAP-04.
 */
final class ProductController
{
    private const AFVOER_REDENEN = ['Verspild', 'Retour leverancier', 'Veeteelt'];

    /** GET /producten — ook de vrijwilliger mag de voorraad zien. */
    public function index(Request $request): array
    {
        $waarschuwing = Settings::int('tht_waarschuwing_dagen', 3);
        $vandaag = new DateTimeImmutable('today');

        $producten = array_map(static function (array $r) use ($vandaag, $waarschuwing): array {
            $dagen = $r['Houdbaar_tot'] ? (int) $vandaag->diff(new DateTimeImmutable($r['Houdbaar_tot']))->format('%r%a') : null;
            return [
                'id'            => (int) $r['idProduct'],
                'naam'          => $r['Productnaam'],
                'ean'           => $r['EAN'],
                'voorraad'      => (int) $r['Aantal_voorraad'],
                'min'           => (int) $r['Min_voorraad'],
                'waarde'        => (float) $r['Waarde'],
                'tht'           => $r['Houdbaar_tot'],
                'thtDagen'      => $dagen,
                'verlopen'      => $dagen !== null && $dagen < 0,
                'bijnaVerlopen' => $dagen !== null && $dagen >= 0 && $dagen <= $waarschuwing,
                'onderMin'      => (int) $r['Aantal_voorraad'] < (int) $r['Min_voorraad'],
                'locatie'       => $r['Locatie'],
                'allergenen'    => $r['Allergenen'] ? explode(',', $r['Allergenen']) : [],
                'categorie'     => ['id' => (int) $r['idCategorie'], 'naam' => $r['CategorieNaam'], 'kort' => $r['CategorieKort']],
                'inPakketten'   => (int) $r['InPakketten'],
            ];
        }, Product::lijst());

        return [
            'producten' => $producten,
            'magazijn'  => [
                'totaal'   => Product::totaleVoorraad(),
                'soorten'  => count($producten),
                'verlopen' => count(array_filter($producten, static fn(array $p): bool => $p['verlopen'])),
                'onderMin' => count(array_filter($producten, static fn(array $p): bool => $p['onderMin'])),
            ],
            'rechten' => [
                'beheren'    => AuthService::can('product_beheren'),
                'corrigeren' => AuthService::can('voorraad_corrigeren'),
            ],
        ];
    }

    /** POST /producten — US-VRD-01 */
    public function store(Request $request): JsonResponse
    {
        $id = Product::maak($this->valideer($request, 0));
        return response()->json(['ok' => true, 'id' => $id, 'melding' => 'Product opgeslagen.'], 201);
    }

    /** PUT /producten/{id} — US-VRD-02 */
    public function update(Request $request): array
    {
        $id = $request->routeId();
        Product::vind($id) ?? throw new HttpException(404, 'Product niet gevonden.');
        Product::wijzig($id, $this->valideer($request, $id));
        return ['ok' => true, 'id' => $id, 'melding' => 'Product opgeslagen.'];
    }

    /** PATCH /producten/{id}/voorraad {aantal} of {delta} — US-VRD-03 */
    public function voorraad(Request $request): array
    {
        $id = $request->routeId();
        $p = Product::vind($id) ?? throw new HttpException(404, 'Product niet gevonden.');

        $nieuw = $request->input('aantal') !== null
            ? $request->getal('aantal')
            : (int) $p['Aantal_voorraad'] + $request->getal('delta');

        if ($nieuw < 0) {
            throw new ValidationException(['aantal' => 'De voorraad kan niet onder 0 komen.']);
        }

        Product::zetVoorraad($id, $nieuw);
        return ['ok' => true, 'voorraad' => $nieuw, 'melding' => 'Voorraad bijgewerkt naar ' . $nieuw . ' stuks.'];
    }

    /** POST /producten/{id}/afvoer {aantal, reden, toelichting} — US-VRD-04 / US-RAP-04 */
    public function afvoeren(Request $request): array
    {
        $id = $request->routeId();
        $aantal = $request->getal('aantal');
        $reden = $request->tekst('reden');
        $toelichting = $request->tekst('toelichting');

        $fouten = [];
        if ($aantal <= 0)                                     { $fouten['aantal'] = 'Vul een aantal groter dan 0 in.'; }
        if (!in_array($reden, self::AFVOER_REDENEN, true))    { $fouten['reden']  = 'Kies een reden.'; }
        ValidationException::throwIf($fouten);

        $p = Product::vind($id) ?? throw new HttpException(404, 'Product niet gevonden.');
        if ((int) $p['Aantal_voorraad'] < $aantal) {
            throw new ValidationException(['aantal' => 'Er liggen maar ' . (int) $p['Aantal_voorraad'] . ' stuks in het magazijn.']);
        }

        DB::transaction(static fn() => Product::voerAf($id, $aantal, $reden, $toelichting ?: null, AuthService::id()));

        return ['ok' => true, 'melding' => $aantal . ' x ' . $p['Productnaam'] . ' afgevoerd (' . $reden . ').'];
    }

    /** DELETE /producten/{id} — alleen zonder historie (US-VRD-05). */
    public function destroy(Request $request): array
    {
        $id = $request->routeId();
        $p = Product::vind($id) ?? throw new HttpException(404, 'Product niet gevonden.');
        $h = Product::historie($id);

        $blokkade = match (true) {
            (int) $h['pakket'] > 0   => ['in_pakket',   (int) $h['pakket'],   'Zet de voorraad op 0 zodat het product niet meer wordt gekozen.'],
            (int) $h['levering'] > 0 => ['in_levering', (int) $h['levering'], 'Het product staat in de leveringshistorie. Zet de voorraad op 0.'],
            (int) $h['afvoer'] > 0   => ['in_afvoer',   (int) $h['afvoer'],   'Zet de voorraad op 0 zodat het product niet meer wordt gekozen.'],
            default                  => null,
        };

        if ($blokkade !== null) {
            [$reden, $aantal, $suggestie] = $blokkade;
            throw new HttpException(409, 'Dit product kan niet worden verwijderd.', [
                'reden' => $reden, 'aantal' => $aantal, 'product' => $p['Productnaam'], 'suggestie' => $suggestie,
            ]);
        }

        Product::verwijder($id);
        return ['ok' => true, 'melding' => $p['Productnaam'] . ' is verwijderd.'];
    }

    private function valideer(Request $request, int $id): array
    {
        $v = [
            'naam'       => $request->tekst('naam'),
            'ean'        => preg_replace('/\D/', '', $request->tekst('ean')),
            'categorie'  => $request->getal('categorie'),
            'voorraad'   => $request->getal('voorraad'),
            'min'        => $request->getal('min'),
            'tht'        => $request->tekst('tht'),
            'locatie'    => $request->tekst('locatie'),
            'allergenen' => $request->tekst('allergenen'),
            'waarde'     => round((float) $request->input('waarde', 0), 2),
        ];

        $fouten = [];
        if (mb_strlen($v['naam']) < 2)                  { $fouten['naam']      = 'Productnaam is verplicht.'; }
        if (!preg_match('/^\d{13}$/', $v['ean']))       { $fouten['ean']       = 'Een EAN bestaat uit 13 cijfers.'; }
        if ($v['categorie'] <= 0 || Categorie::vind($v['categorie']) === null) {
            $fouten['categorie'] = 'Kies een categorie.';
        }
        if ($v['voorraad'] < 0)                         { $fouten['voorraad']  = 'Voorraad kan niet negatief zijn.'; }
        if ($v['min'] < 0)                              { $fouten['min']       = 'Minimumvoorraad kan niet negatief zijn.'; }
        if ($v['waarde'] < 0 || $v['waarde'] > 9999)    { $fouten['waarde']    = 'Vul een bedrag tussen 0 en 9999 in.'; }
        if ($v['tht'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['tht'])) { $fouten['tht'] = 'Ongeldige datum.'; }

        // uniek: productnaam (US-VRD-10) en streepjescode (US-VRD-09)
        foreach (Product::dubbel($v['naam'], $v['ean'], $id) as $r) {
            if (strcasecmp($r['Productnaam'], $v['naam']) === 0) { $fouten['naam'] = 'Dit product bestaat al in het magazijn.'; }
            if ($r['EAN'] === $v['ean'])                        { $fouten['ean']  = 'Deze streepjescode is al in gebruik.'; }
        }

        ValidationException::throwIf($fouten);
        return $v;
    }
}
