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
use App\Models\Product;
use App\Models\Wens;
use App\Services\DieetControle;
use App\Services\Weekcyclus;

/**
 * Voedselpakketten samenstellen — US-PAK-01 t/m US-PAK-12.
 */
final class PakketController
{
    private const STATUSSEN = ['Concept', 'Klaar'];

    /** GET /pakketten/samenstellen — klanten van deze week en de hele voorraad. */
    public function samenstellen(Request $request): array
    {
        $maandag = Weekcyclus::weekStart();

        return [
            'weekstart' => $maandag,
            'klanten'   => array_map(static fn(array $k): array => [
                'id'            => (int) $k['idKlant'],
                'naam'          => $k['Gezinsnaam'],
                'gezinsgrootte' => (int) $k['Gezinsgrootte'],
                'dezeWeek'      => (int) $k['Deze_week'] > 0,
                'weekStatus'    => $k['Week_status'],
                'laatste'       => $k['Laatste'],
                'advies'        => Weekcyclus::advies((int) $k['Gezinsgrootte']),
            ], Pakket::klantenVoorWeek($maandag)),
            'producten' => array_map(static fn(array $p): array => [
                'id'          => (int) $p['idProduct'],
                'naam'        => $p['Productnaam'],
                'voorraad'    => (int) $p['Aantal_voorraad'],
                'tht'         => $p['Houdbaar_tot'],
                'thtDagen'    => $p['Houdbaar_tot'] ? (int) floor((strtotime($p['Houdbaar_tot']) - strtotime('today')) / 86400) : null,
                'verlopen'    => DieetControle::isVerlopen($p['Houdbaar_tot']),
                'allergenen'  => $p['Allergenen'],
                'locatie'     => $p['Locatie'],
                'categorie'   => $p['Categorie'],
                'categorieId' => (int) $p['idCategorie'],
            ], Product::voorSamenstellen()),
        ];
    }

    /** GET /pakketten/klant/{id} — gezin, dieetprofiel, advies en het lopende pakket (US-PAK-02/03). */
    public function klant(Request $request): array
    {
        $id = $request->routeId();
        $klant = Klant::vind($id) ?? throw new HttpException(404, 'Klant niet gevonden.');

        $leden = Klant::leden($id);
        $wensenPerLid = Wens::perLid($id);
        $lopend = Pakket::vanDezeWeek($id, Weekcyclus::weekStart());

        return [
            'klant' => [
                'id'          => (int) $klant['idKlant'],
                'naam'        => $klant['Gezinsnaam'],
                'opmerkingen' => $klant['Opmerkingen'],
            ],
            'gezin' => array_map(static fn(array $g): array => [
                'id'       => (int) $g['idGezinslid'],
                'naam'     => $g['Naam'],
                'leeftijd' => (int) $g['Leeftijd'],
                'type'     => $g['Type'],
                'relatie'  => $g['Relatie'],
                'wensen'   => $wensenPerLid[(int) $g['idGezinslid']] ?? [],
            ], $leden),
            'wensen' => array_values(DieetControle::profiel($id)),
            'advies' => Weekcyclus::advies(count($leden)),
            'lopend' => $lopend ? [
                'id'     => (int) $lopend['idPakket'],
                'status' => $lopend['Status'],
                'datum'  => $lopend['Samenstellingsdatum'],
            ] : null,
        ];
    }

    /** GET /pakketten — overzicht van alle pakketten. */
    public function index(Request $request): array
    {
        return ['pakketten' => array_map(static fn(array $r): array => [
            'id'        => (int) $r['idPakket'],
            'klantId'   => (int) $r['idKlant'],
            'klant'     => $r['Gezinsnaam'],
            'status'    => $r['Status'],
            'datum'     => $r['Samenstellingsdatum'],
            'uitgifte'  => $r['Uitgiftedatum'],
            'opgehaald' => (bool) $r['Opgehaald'],
            'aantal'    => (int) $r['Aantal_producten'],
            'door'      => $r['Samensteller'],
            'uitgever'  => $r['Uitgever'],
        ], Pakket::lijst())];
    }

    /** GET /pakketten/{id} — één pakket met inhoud; ook voor de pakbon (US-PAK-10/11). */
    public function show(Request $request): array
    {
        $id = $request->routeId();
        $p = Pakket::vind($id) ?? throw new HttpException(404, 'Pakket niet gevonden.');
        $klantId = (int) $p['idKlant'];
        $profiel = DieetControle::profiel($klantId);

        return [
            'pakket' => [
                'id'            => (int) $p['idPakket'],
                'klantId'       => $klantId,
                'klant'         => $p['Gezinsnaam'],
                'status'        => $p['Status'],
                'datum'         => $p['Samenstellingsdatum'],
                'uitgifte'      => $p['Uitgiftedatum'],
                'opgehaald'     => (bool) $p['Opgehaald'],
                'gecontroleerd' => (bool) $p['Identiteit_gecontroleerd'],
                'legitimatie'   => $p['Legitimatie'],
                'door'          => $p['Samensteller'],
                'uitgever'      => $p['Uitgever'],
                'opmerkingen'   => $p['Opmerkingen'],
            ],
            // waarom een geblokkeerd product toch mee mag; staat op de pakbon
            'uitzonderingen' => array_values(array_map(
                static fn(array $w): array => ['wens' => $w['wens'], 'toelichting' => $w['toelichting']],
                array_filter($profiel, static fn(array $w): bool => $w['uitzondering'])
            )),
            'inhoud' => array_map(static fn(array $r): array => [
                'productId' => (int) $r['idProduct'],
                'naam'      => $r['Productnaam'],
                'aantal'    => (int) $r['Aantal'],
                'categorie' => $r['Categorie'],
                'locatie'   => $r['Locatie'],
                'conflict'  => DieetControle::botsingen($r['Allergenen'], $profiel),
            ], Pakket::inhoud($id)),
            'gezin'  => array_map(static fn(array $g): array => [
                'Naam' => $g['Naam'], 'Leeftijd' => (int) $g['Leeftijd'], 'Type' => $g['Type'],
            ], Klant::leden($klantId)),
            'wensen' => Klant::wensNamen($klantId),
        ];
    }

    /** POST /pakketten {klant, status, regels[], forceer} — nieuw pakket. */
    public function store(Request $request): JsonResponse
    {
        return response()->json($this->opslaan($request, 0), 201);
    }

    /** PUT /pakketten/{id} — bestaand concept of klaargezet pakket wijzigen. */
    public function update(Request $request): array
    {
        return $this->opslaan($request, $request->routeId());
    }

    /** DELETE /pakketten/{id} — producten gaan terug in de voorraad. */
    public function destroy(Request $request): array
    {
        $id = $request->routeId();
        $status = Pakket::status($id) ?? throw new HttpException(404, 'Pakket niet gevonden.');

        if ($status === 'Uitgegeven') {
            throw new HttpException(409, 'Dit pakket is al uitgegeven. Uitgegeven pakketten blijven bewaard voor de rapportages.',
                ['reden' => 'is_historie']);
        }

        DB::transaction(static fn() => Pakket::verwijderEnTerugboeken($id));
        return ['ok' => true, 'melding' => 'Pakket verwijderd, de producten staan weer in de voorraad.'];
    }

    /**
     * Opslaan als Concept of Klaar (US-PAK-05/07/08). Controles in deze volgorde:
     *   1. invoer klopt                                  -> 422
     *   2. klant bestaat en is geaccepteerd              -> 404 / 422
     *   3. pakket is nog niet uitgegeven                 -> 409
     *   4. maximaal één pakket per gezin per week        -> 409 dubbel_deze_week
     *   5. geen verlopen product of strenge allergie     -> 409 geblokkeerd (ook niet met forceer)
     *   6. overige dieetwensen alleen met bevestiging    -> 409 conflict, tenzij forceer
     *   7. genoeg voorraad                               -> 409 te_weinig_voorraad
     */
    private function opslaan(Request $request, int $id): array
    {
        $klantId = $request->getal('klant');
        $status = $request->tekst('status') ?: 'Concept';
        $forceer = $request->vink('forceer');
        $regels = $this->regels($request->lijst('regels'));

        if (!in_array($status, self::STATUSSEN, true)) {
            throw new ValidationException(['status' => 'Ongeldige status.']);
        }
        $fouten = [];
        if ($klantId <= 0)                       { $fouten['klant']  = 'Kies een klant.'; }
        if (!$regels && $status === 'Klaar')     { $fouten['regels'] = 'Een pakket dat klaar is moet minimaal één product bevatten.'; }
        ValidationException::throwIf($fouten);

        $klant = Klant::vind($klantId) ?? throw new HttpException(404, 'Klant niet gevonden.');
        if ((int) $klant['Geaccepteerd'] !== 1) {
            throw new HttpException(422, 'Deze klant is nog niet geaccepteerd en mag geen pakket krijgen.');
        }

        if ($id > 0) {
            $huidig = Pakket::status($id) ?? throw new HttpException(404, 'Pakket niet gevonden.');
            if ($huidig === 'Uitgegeven') {
                throw new HttpException(409, 'Dit pakket is al uitgegeven en kan niet meer worden gewijzigd.');
            }
        }

        if (Pakket::anderPakketDezeWeek($klantId, Weekcyclus::weekStart(), $id)) {
            throw new HttpException(409, $klant['Gezinsnaam'] . ' heeft deze week al een pakket.', ['reden' => 'dubbel_deze_week']);
        }

        $controle = DieetControle::controleer(Product::vindMeerdere(array_keys($regels)), DieetControle::profiel($klantId));
        if ($controle['geblokkeerd']) {
            throw new HttpException(409, 'Een of meer producten mogen niet in dit pakket.',
                ['reden' => 'geblokkeerd', 'geblokkeerd' => $controle['geblokkeerd']]);
        }
        if ($controle['waarschuwing'] && !$forceer) {
            throw new HttpException(409, 'Een of meer producten passen niet bij de wensen van dit gezin.',
                ['reden' => 'conflict', 'conflicten' => $controle['waarschuwing']]);
        }

        $id = DB::transaction(function () use ($id, $klantId, $status, $regels): int {
            $oud = $id > 0 ? Pakket::aantallen($id) : [];
            $this->controleerVoorraad($regels, $oud);

            if ($id === 0) {
                $id = Pakket::maak($klantId, $status, AuthService::id());
            }
            Pakket::vervangInhoud($id, $oud, $regels);
            Pakket::zetStatus($id, $status);
            return $id;
        });

        return [
            'ok'      => true,
            'id'      => $id,
            'melding' => $status === 'Klaar' ? 'Pakket is klaargezet voor ' . $klant['Gezinsnaam'] . '.' : 'Concept opgeslagen.',
        ];
    }

    /** Alleen het verschil met de oude inhoud moet nog op voorraad zijn (US-PAK-07). */
    private function controleerVoorraad(array $nieuw, array $oud): void
    {
        $tekort = [];
        foreach ($nieuw as $pid => $aantal) {
            $erbij = $aantal - ($oud[$pid] ?? 0);
            if ($erbij <= 0) {
                continue;
            }
            $p = Product::voorraadVoorUpdate($pid);
            if ($p === null || (int) $p['Aantal_voorraad'] < $erbij) {
                $tekort[] = [
                    'product'  => $p['Productnaam'] ?? ('#' . $pid),
                    'gevraagd' => $erbij,
                    'voorraad' => (int) ($p['Aantal_voorraad'] ?? 0),
                ];
            }
        }
        if ($tekort) {
            throw new HttpException(409, 'Er is niet genoeg voorraad voor dit pakket.', ['reden' => 'te_weinig_voorraad', 'tekort' => $tekort]);
        }
    }

    /** @return array<int,int> productId => aantal, dubbele regels opgeteld */
    private function regels(array $invoer): array
    {
        $uit = [];
        foreach ($invoer as $r) {
            $pid = (int) ($r['product'] ?? 0);
            $aantal = (int) ($r['aantal'] ?? 0);
            if ($pid > 0 && $aantal > 0) {
                $uit[$pid] = ($uit[$pid] ?? 0) + $aantal;
            }
        }
        return $uit;
    }
}
