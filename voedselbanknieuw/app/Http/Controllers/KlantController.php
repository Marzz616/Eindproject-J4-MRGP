<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use App\Exceptions\HttpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\ValidationException;
use App\Models\Klant;
use App\Models\Pakket;
use App\Models\Wens;

/**
 * Klanten- en gezinnenadministratie — US-KLT-01 t/m US-KLT-14, US-SEC-02.
 *
 * Contactgegevens (adres, telefoon, e-mail) zijn privacygevoelig. Wie geen
 * klant_beheren heeft, krijgt ze niet eens mee in het antwoord van de server.
 */
final class KlantController
{
    private const RELATIES = ['Hoofdaanvrager', 'Partner', 'Ouder (Moeder)', 'Ouder (Vader)', 'Kind', 'Inwonend'];

    /** GET /klanten */
    public function index(Request $request): array
    {
        $contact = AuthService::can('klant_beheren');
        $rechten = ['beheren' => AuthService::can('klant_beheren'), 'contact' => $contact];

        $klanten = Klant::lijst();
        if (!$klanten) {
            return ['klanten' => [], 'rechten' => $rechten];
        }

        $ids = array_map('intval', array_column($klanten, 'idKlant'));
        $ledenPerKlant = $this->ledenPerKlant($ids);

        $uit = [];
        foreach ($klanten as $k) {
            $id = (int) $k['idKlant'];
            $rij = [
                'id'             => $id,
                'nummer'         => $k['Klantnummer'],
                'naam'           => $k['Gezinsnaam'],
                'aanmeld'        => $k['Aanmelddatum'],
                'status'         => $k['Geaccepteerd'] === null ? null : (bool) $k['Geaccepteerd'],
                'laatste'        => $k['Laatste_uitgifte'],
                'grootte'        => (int) $k['Gezinsgrootte'],
                'pakketDezeWeek' => (int) $k['PakketDezeWeek'] > 0,
                'opmerkingen'    => $k['Opmerkingen'],
                'leden'          => $ledenPerKlant[$id] ?? [],
            ];
            if ($contact) {
                $rij += [
                    'adres'    => $k['Adres'],
                    'postcode' => $k['Postcode'],
                    'plaats'   => $k['Plaats'],
                    'telefoon' => $k['Telefoon'],
                    'email'    => $k['Email'],
                ];
            }
            $uit[] = $rij;
        }

        return ['klanten' => $uit, 'rechten' => $rechten];
    }

    /** POST /klanten — nieuwe klant registreren (alleen met klant_beheren). */
    public function store(Request $request): JsonResponse
    {
        $contact = $this->valideerContact($request);
        $leden = $this->valideerLeden($request);

        $id = DB::transaction(static function () use ($contact, $leden): int {
            $id = Klant::maak($contact);
            Klant::vervangLeden($id, $leden);
            return $id;
        });

        return response()->json(['ok' => true, 'id' => $id, 'melding' => 'Klantgegevens opgeslagen.'], 201);
    }

    /**
     * PUT /klanten/{id}
     * De directie wijzigt alles; een vrijwilliger alleen de gezinssamenstelling
     * en de dieetwensen (US-KLT-05 t/m 10).
     */
    public function update(Request $request): array
    {
        $id = $request->routeId();
        Klant::vind($id) ?? throw new HttpException(404, 'Klant niet gevonden.');

        $volledig = AuthService::can('klant_beheren');
        $contact = $volledig ? $this->valideerContact($request) : null;
        $leden = $this->valideerLeden($request);

        DB::transaction(static function () use ($id, $contact, $leden): void {
            if ($contact !== null) {
                Klant::wijzigContact($id, $contact);
            }
            Klant::vervangLeden($id, $leden);
        });

        return ['ok' => true, 'id' => $id, 'melding' => 'Klantgegevens opgeslagen.'];
    }

    /** DELETE /klanten/{id} — alleen zonder pakkethistorie. */
    public function destroy(Request $request): array
    {
        $id = $request->routeId();
        $k = Klant::vind($id) ?? throw new HttpException(404, 'Klant niet gevonden.');

        // Historie beschermen: een gezin dat ooit een pakket kreeg blijft staan,
        // anders kloppen de rapportages niet meer.
        $aantal = Klant::aantalPakketten($id);
        if ($aantal > 0) {
            throw new HttpException(409,
                $k['Gezinsnaam'] . ' heeft ' . $aantal . ' pakket' . ($aantal === 1 ? '' : 'ten') . ' ontvangen. '
                . 'Die geschiedenis blijft bewaard voor de rapportages.', [
                'reden'     => 'heeft_historie',
                'aantal'    => $aantal,
                'klant'     => $k['Gezinsnaam'],
                'suggestie' => 'Zet de klant op non-actief. Hij verdwijnt dan uit de keuzelijsten '
                             . 'bij het samenstellen, maar de pakketten blijven in de rapportages staan.',
            ]);
        }

        Klant::verwijder($id);
        return ['ok' => true, 'melding' => $k['Gezinsnaam'] . ' is verwijderd, inclusief de gezinsleden en hun dieetwensen.'];
    }

    /** PATCH /klanten/{id}/status {geaccepteerd: true|false|null} — US-KLT-12 */
    public function status(Request $request): array
    {
        $id = $request->routeId();
        $k = Klant::vind($id) ?? throw new HttpException(404, 'Klant niet gevonden.');

        $waarde = $request->input('geaccepteerd');
        $status = $waarde === null ? null : (bool) $waarde;
        Klant::zetStatus($id, $status);

        $naam = $k['Gezinsnaam'];
        return ['ok' => true, 'melding' => match ($status) {
            true    => $naam . ' is geaccepteerd en kan pakketten ontvangen.',
            false   => $naam . ' staat nu op non-actief.',
            null    => $naam . ' wacht weer op beoordeling.',
        }];
    }

    /** GET /klanten/{id}/pakketten — pakketgeschiedenis (US-KLT-13/14). */
    public function pakketten(Request $request): array
    {
        $pakketten = Klant::pakketten($request->routeId());
        $regels = Pakket::regelsVan(array_map('intval', array_column($pakketten, 'idPakket')));

        return array_map(static fn(array $p): array => [
            'id'        => (int) $p['idPakket'],
            'samen'     => $p['Samenstellingsdatum'],
            'uitgifte'  => $p['Uitgiftedatum'],
            'status'    => $p['Status'],
            'opgehaald' => (bool) $p['Opgehaald'],
            'regels'    => $regels[(int) $p['idPakket']] ?? [],
        ], $pakketten);
    }

    /** GET /wensen — keuzelijst dieetwensen. */
    public function wensen(Request $request): array
    {
        return array_map(static fn(array $w): array => [
            'id' => (int) $w['idWens'], 'naam' => $w['Naam'], 'streng' => (bool) $w['Streng'],
        ], Wens::lijst());
    }

    /** POST /wensen {naam, streng, tag} — eigen wens toevoegen (US-KLT-10). */
    public function wensToevoegen(Request $request): JsonResponse
    {
        $naam = $request->tekst('naam');
        if (mb_strlen($naam) < 2) {
            throw new ValidationException(['naam' => 'Geef een omschrijving van de wens.']);
        }

        if ($bestaand = Wens::opNaam($naam)) {
            return response()->json(['id' => (int) $bestaand['idWens'], 'naam' => $bestaand['Naam'], 'streng' => (bool) $bestaand['Streng']]);
        }

        $tag = $request->tekst('tag');
        $streng = $request->vink('streng');
        $id = Wens::maak($naam, $streng, $tag !== '' ? mb_strtolower($tag) : null);

        return response()->json(['id' => $id, 'naam' => $naam, 'streng' => $streng], 201);
    }

    /* ------------------------------------------------------------ hulpjes */

    /** Gezinsleden met hun wensen, gegroepeerd per klant. */
    private function ledenPerKlant(array $klantIds): array
    {
        $wensenPerLid = [];
        foreach (Klant::wensenVan($klantIds) as $w) {
            $wensenPerLid[(int) $w['lid']][] = [
                'id'           => (int) $w['idWens'],
                'naam'         => $w['Naam'],
                'streng'       => (bool) $w['Streng'],
                'uitzondering' => (int) $w['Toch_toestaan'] === 1,
                'toelichting'  => $w['Toelichting'],
            ];
        }

        $uit = [];
        foreach (Klant::ledenVan($klantIds) as $l) {
            $uit[(int) $l['Klant_idKlant']][] = [
                'id'       => (int) $l['idGezinslid'],
                'naam'     => $l['Naam'],
                'gb'       => $l['Geboortedatum'],
                'relatie'  => $l['Relatie'],
                'leeftijd' => (int) $l['Leeftijd'],
                'type'     => $l['Type'],
                'wensen'   => $wensenPerLid[(int) $l['idGezinslid']] ?? [],
            ];
        }
        return $uit;
    }

    private function valideerContact(Request $request): array
    {
        $v = [
            'naam'        => $request->tekst('naam'),
            'adres'       => $request->tekst('adres'),
            'postcode'    => strtoupper(str_replace(' ', '', $request->tekst('postcode'))),
            'plaats'      => $request->tekst('plaats') ?: 'Maaskantje',
            'telefoon'    => $request->tekst('telefoon'),
            'email'       => $request->tekst('email'),
            'opmerkingen' => mb_substr($request->tekst('opmerkingen'), 0, 500),
        ];

        $fouten = [];
        if (mb_strlen($v['naam']) < 2)                             { $fouten['naam']     = 'Gezinsnaam is verplicht.'; }
        if (mb_strlen($v['adres']) < 3)                            { $fouten['adres']    = 'Adres is verplicht.'; }
        if (!preg_match('/^[1-9][0-9]{3}[A-Z]{2}$/', $v['postcode'])) { $fouten['postcode'] = 'Bijvoorbeeld 5492 AB.'; }
        if (mb_strlen($v['telefoon']) < 9)                         { $fouten['telefoon'] = 'Telefoonnummer is verplicht.'; }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL))       { $fouten['email']    = 'Vul een geldig e-mailadres in.'; }

        // ook de leden meteen controleren, zodat alle fouten in één keer terugkomen
        if (!$this->geldigeLeden($request->lijst('leden'))) {
            $fouten['leden'] = 'Voeg minimaal één gezinslid toe.';
        }
        ValidationException::throwIf($fouten);
        return $v;
    }

    /** Gezinsleden opschonen: zonder naam of geboortedatum tellen ze niet mee. */
    private function valideerLeden(Request $request): array
    {
        $leden = $this->geldigeLeden($request->lijst('leden'));
        if (!$leden) {
            throw new ValidationException(['leden' => 'Voeg minimaal één gezinslid toe.']);
        }
        return $leden;
    }

    private function geldigeLeden(array $invoer): array
    {
        $uit = [];
        foreach ($invoer as $lid) {
            $naam = trim((string) ($lid['naam'] ?? ''));
            $gb = (string) ($lid['gb'] ?? '');
            if ($naam === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gb)) {
                continue;
            }
            $relatie = (string) ($lid['relatie'] ?? 'Kind');
            $uit[] = [
                'naam'           => $naam,
                'gb'             => $gb,
                'relatie'        => in_array($relatie, self::RELATIES, true) ? $relatie : 'Kind',
                'wensen'         => array_map('intval', (array) ($lid['wensen'] ?? [])),
                'uitzonderingen' => (array) ($lid['uitzonderingen'] ?? []),
            ];
        }
        return $uit;
    }
}
