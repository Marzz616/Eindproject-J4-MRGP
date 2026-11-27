<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use App\Exceptions\HttpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\ValidationException;
use App\Models\Leverancier;

/**
 * Leveranciers en leveringen — US-LEV-01 t/m US-LEV-06.
 */
final class LeverancierController
{
    private const CATEGORIEEN = ['Supermarkt', 'Bakkerij', 'Groothandel', 'Zuivel', 'Kweker', 'Particulier', 'Overig'];

    /** GET /leveranciers — met leveringsgeschiedenis. */
    public function index(Request $request): array
    {
        $perLeverancier = [];
        foreach (Leverancier::leveringen() as $r) {
            $perLeverancier[(int) $r['Leverancier_idLeverancier']][] = [
                'id'     => (int) $r['idLevering'],
                'datum'  => $r['Leverdatum'],
                'tijd'   => $r['Levertijd'] ? substr((string) $r['Levertijd'], 0, 5) : null,
                'inhoud' => $r['Inhoud'] ?? '',
                'totaal' => (int) $r['Totaal'],
                'door'   => $r['Verwerkt_door'],
            ];
        }

        return [
            'leveranciers' => array_map(static fn(array $l): array => [
                'id'             => (int) $l['idLeverancier'],
                'bedrijfsnaam'   => $l['Bedrijfsnaam'],
                'adres'          => $l['Adres'],
                'postcode'       => $l['Postcode'],
                'plaats'         => $l['Plaats'],
                'contactpersoon' => $l['Contactpersoon'],
                'email'          => $l['Email'],
                'telefoon'       => $l['Telefoon'],
                'categorie'      => $l['Categorie'],
                'actief'         => (bool) $l['Actief'],
                'volgendeDatum'  => $l['Volgende_leverdatum'],
                'volgendeTijd'   => $l['Volgende_levertijd'] ? substr((string) $l['Volgende_levertijd'], 0, 5) : null,
                'aantal'         => (int) $l['Aantal_leveringen'],
                'laatste'        => $l['Laatste_levering'],
                'leveringen'     => $perLeverancier[(int) $l['idLeverancier']] ?? [],
            ], Leverancier::lijst()),
            'rechten' => ['beheren' => AuthService::can('leverancier_beheren'), 'boeken' => AuthService::can('levering_boeken')],
        ];
    }

    /** POST /leveranciers — US-LEV-01 */
    public function store(Request $request): JsonResponse
    {
        $id = Leverancier::maak($this->valideer($request, 0));
        return response()->json(['ok' => true, 'id' => $id, 'melding' => 'Leverancier toegevoegd.'], 201);
    }

    /** PUT /leveranciers/{id} — US-LEV-02 */
    public function update(Request $request): array
    {
        $id = $request->routeId();
        Leverancier::vind($id) ?? throw new HttpException(404, 'Leverancier niet gevonden.');
        Leverancier::wijzig($id, $this->valideer($request, $id));
        return ['ok' => true, 'id' => $id, 'melding' => 'Leverancier bijgewerkt.'];
    }

    /** PATCH /leveranciers/{id}/actief {actief} — US-LEV-03, de zachte variant van verwijderen. */
    public function actief(Request $request): array
    {
        $id = $request->routeId();
        Leverancier::vind($id) ?? throw new HttpException(404, 'Leverancier niet gevonden.');
        $aan = $request->vink('actief', true);
        Leverancier::zetActief($id, $aan);
        return ['ok' => true, 'melding' => $aan ? 'Leverancier geactiveerd.' : 'Leverancier op non-actief gezet.'];
    }

    /** DELETE /leveranciers/{id} — alleen zonder leveringshistorie. */
    public function destroy(Request $request): array
    {
        $id = $request->routeId();
        Leverancier::vind($id) ?? throw new HttpException(404, 'Leverancier niet gevonden.');

        $aantal = Leverancier::aantalLeveringen($id);
        if ($aantal > 0) {
            throw new HttpException(409,
                "Deze leverancier heeft {$aantal} geregistreerde levering(en). Die geschiedenis mag niet verdwijnen.", [
                'reden'     => 'heeft_historie',
                'suggestie' => 'Zet de leverancier op non-actief. Hij verdwijnt dan uit de keuzelijsten, '
                             . 'maar de leveringen blijven in de rapportages staan.',
            ]);
        }

        Leverancier::verwijder($id);
        return ['ok' => true, 'melding' => 'Leverancier verwijderd.'];
    }

    /** POST /leveringen {leverancier, datum, tijd, regels[]} — US-LEV-04/05, voorraad gaat automatisch omhoog. */
    public function levering(Request $request): JsonResponse
    {
        $leverancierId = $request->getal('leverancier');
        $datum = $request->tekst('datum') ?: date('Y-m-d');
        $tijd = $request->tekst('tijd') ?: null;

        // regels opschonen en per product optellen
        $regels = [];
        foreach ($request->lijst('regels') as $r) {
            $pid = (int) ($r['product'] ?? 0);
            $aantal = (int) ($r['aantal'] ?? 0);
            if ($pid > 0 && $aantal > 0) {
                $regels[$pid] = ($regels[$pid] ?? 0) + $aantal;
            }
        }

        $fouten = [];
        if ($leverancierId <= 0 || Leverancier::vind($leverancierId) === null) {
            $fouten['leverancier'] = 'Kies een leverancier.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) { $fouten['datum']  = 'Vul een geldige datum in.'; }
        if (!$regels)                                      { $fouten['regels'] = 'Voeg minimaal één product toe.'; }
        ValidationException::throwIf($fouten);

        $id = DB::transaction(static fn(): int =>
            Leverancier::boekLevering($leverancierId, $datum, $tijd, AuthService::id(), $regels));

        return response()->json(['ok' => true, 'id' => $id, 'melding' => 'Levering geboekt en voorraad bijgewerkt.'], 201);
    }

    private function valideer(Request $request, int $id): array
    {
        $v = [
            'bedrijfsnaam'   => $request->tekst('bedrijfsnaam'),
            'adres'          => $request->tekst('adres'),
            'postcode'       => strtoupper($request->tekst('postcode')),
            'plaats'         => $request->tekst('plaats'),
            'contactpersoon' => $request->tekst('contactpersoon'),
            'email'          => $request->tekst('email'),
            'telefoon'       => $request->tekst('telefoon'),
            'categorie'      => $request->tekst('categorie') ?: 'Overig',
            'volgendeDatum'  => $request->tekst('volgendeDatum') ?: null,
            'volgendeTijd'   => $request->tekst('volgendeTijd') ?: null,
        ];

        $fouten = [];
        if ($v['bedrijfsnaam'] === '')   { $fouten['bedrijfsnaam']   = 'Vul de bedrijfsnaam in.'; }
        if ($v['adres'] === '')          { $fouten['adres']          = 'Vul het adres in.'; }
        if ($v['contactpersoon'] === '') { $fouten['contactpersoon'] = 'Vul de contactpersoon in.'; }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $fouten['email'] = 'Vul een geldig e-mailadres in.';
        }
        if (!preg_match('/^[0-9 +\-]{10,20}$/', $v['telefoon'])) {
            $fouten['telefoon'] = 'Vul een geldig telefoonnummer in.';
        }
        if ($v['postcode'] !== '' && !preg_match('/^[1-9][0-9]{3} ?[A-Z]{2}$/', $v['postcode'])) {
            $fouten['postcode'] = 'Gebruik het formaat 1234 AB.';
        }
        if (!in_array($v['categorie'], self::CATEGORIEEN, true)) {
            $fouten['categorie'] = 'Kies een categorie uit de lijst.';
        }
        if ($v['volgendeDatum'] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['volgendeDatum'])) {
            $fouten['volgendeDatum'] = 'Vul een geldige datum in.';
        }
        if (!isset($fouten['bedrijfsnaam']) && Leverancier::naamBestaat($v['bedrijfsnaam'], $id)) {
            $fouten['bedrijfsnaam'] = 'Er bestaat al een leverancier met deze naam.';
        }

        ValidationException::throwIf($fouten);
        return $v;
    }
}
