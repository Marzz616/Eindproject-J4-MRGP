<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\ValidationException;
use App\Models\Klant;
use App\Models\Pakket;
use App\Services\Weekcyclus;

/**
 * Vrijdag uitgiftepunt — US-PAK-09, US-PAK-12, US-WRK-01 t/m US-WRK-06.
 *
 * Volgorde aan de balie: klant zoeken -> legitimatie vastleggen -> pakket uitgeven.
 * Zonder vastgelegde legitimatie kan een pakket niet worden uitgegeven.
 */
final class UitgifteController
{
    private const LEGITIMATIE = ['Paspoort', 'Rijbewijs', 'ID-kaart'];

    /** GET /uitgifte/wachtrij — alle pakketten van deze week, per status. */
    public function wachtrij(Request $request): array
    {
        $maandag = Weekcyclus::weekStart();
        $uit = ['weekstart' => $maandag, 'klaar' => [], 'concept' => [], 'uitgegeven' => []];

        foreach (Pakket::wachtrij($maandag) as $r) {
            $groep = match ($r['Status']) { 'Klaar' => 'klaar', 'Uitgegeven' => 'uitgegeven', default => 'concept' };
            $uit[$groep][] = [
                'pakketId'      => (int) $r['idPakket'],
                'nummer'        => $r['Klantnummer'],
                'klant'         => $r['Gezinsnaam'],
                'gezinsgrootte' => (int) $r['Gezinsgrootte'],
                'aantal'        => (int) $r['Aantal'],
                'status'        => $r['Status'],
                'uitgifte'      => $r['Uitgiftedatum'],
                'gecontroleerd' => (bool) $r['Identiteit_gecontroleerd'],
            ];
        }
        return $uit;
    }

    /** GET /uitgifte/{id} — één pakketkaart. */
    public function show(Request $request): array
    {
        return $this->kaart($request->id()) ?? throw new HttpException(404, 'Pakket niet gevonden.');
    }

    /** GET /uitgifte/zoek?q= — op klantnummer of naam ("scan pasje of typ cliënt-ID"). */
    public function zoek(Request $request): array
    {
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            throw new ValidationException(['q' => 'Vul een cliëntnummer of naam in.']);
        }

        $klantIds = Pakket::zoekKlanten($term);
        if (!$klantIds) {
            throw new HttpException(404, 'Geen cliënt gevonden voor "' . $term . '".');
        }

        $maandag = Weekcyclus::weekStart();
        $resultaten = [];
        foreach ($klantIds as $klantId) {
            $pakketId = Pakket::relevantVoorUitgifte($klantId, $maandag);
            $resultaten[] = $pakketId !== null ? $this->kaart($pakketId) : $this->kaartZonderPakket($klantId);
        }
        return ['resultaten' => $resultaten];
    }

    /** POST /uitgifte/{id}/identiteit {legitimatie} — US-WRK-01 */
    public function identiteit(Request $request): array
    {
        $soort = $request->string('legitimatie');
        if (!in_array($soort, self::LEGITIMATIE, true)) {
            throw new ValidationException(['legitimatie' => 'Kies paspoort, rijbewijs of ID-kaart.']);
        }
        if (!Pakket::legitimeer($request->id(), $soort)) {
            throw new HttpException(409, 'Dit pakket bestaat niet of is al uitgegeven.');
        }
        return ['ok' => true, 'melding' => 'Geïdentificeerd met: ' . $soort];
    }

    /** POST /uitgifte/{id}/uitgeven — US-PAK-09 / US-PAK-12 */
    public function uitgeven(Request $request): array
    {
        $id = $request->id();
        $p = Pakket::vind($id) ?? throw new HttpException(404, 'Pakket niet gevonden.');

        if ($p['Status'] === 'Uitgegeven') {
            throw new HttpException(409, 'Dit pakket is al uitgegeven.', ['reden' => 'al_uitgegeven']);
        }
        if (!Pakket::aantallen($id)) {
            throw new HttpException(409, 'Dit pakket is nog leeg.', ['reden' => 'leeg']);
        }
        if (!(int) $p['Identiteit_gecontroleerd']) {
            throw new HttpException(409, 'Leg eerst vast met welk legitimatiebewijs de cliënt zich heeft geïdentificeerd.',
                ['reden' => 'geen_identiteit']);
        }

        Pakket::geefUit($id, Auth::id());
        return ['ok' => true, 'melding' => 'Pakket uitgegeven aan ' . $p['Gezinsnaam'] . '.'];
    }

    /** POST /uitgifte/{id}/niet-opgehaald — pakket terugboeken naar de voorraad (US-WRK-04). */
    public function nietOpgehaald(Request $request): array
    {
        $id = $request->id();
        $status = Pakket::status($id) ?? throw new HttpException(404, 'Pakket niet gevonden.');
        if ($status === 'Uitgegeven') {
            throw new HttpException(409, 'Dit pakket is al uitgegeven en blijft in de historie staan.');
        }

        Database::transaction(static fn() => Pakket::verwijderEnTerugboeken($id));
        return ['ok' => true, 'melding' => 'Pakket teruggeboekt, de producten staan weer in de voorraad.'];
    }

    /** Alles wat het uitgiftescherm van één pakket toont. */
    private function kaart(int $pakketId): ?array
    {
        $p = Pakket::vind($pakketId);
        if ($p === null) {
            return null;
        }

        return [
            'pakketId'      => (int) $p['idPakket'],
            'status'        => $p['Status'],
            'datum'         => $p['Samenstellingsdatum'],
            'uitgifte'      => $p['Uitgiftedatum'],
            'opgehaald'     => (bool) $p['Opgehaald'],
            'gecontroleerd' => (bool) $p['Identiteit_gecontroleerd'],
            'legitimatie'   => $p['Legitimatie'],
            'klant'         => [
                'id'            => (int) $p['idKlant'],
                'nummer'        => $p['Klantnummer'],
                'naam'          => $p['Gezinsnaam'],
                'adres'         => $p['Adres'] . ', ' . $p['Plaats'],
                'gezinsgrootte' => (int) $p['Gezinsgrootte'],
            ],
            'inhoud' => array_map(static fn(array $r): array => [
                'naam' => $r['Productnaam'], 'aantal' => (int) $r['Aantal'], 'categorie' => $r['Categorie'],
            ], Pakket::inhoud($pakketId)),
            'wensen' => Klant::wensNamen((int) $p['idKlant']),
        ];
    }

    private function kaartZonderPakket(int $klantId): array
    {
        $k = Klant::vind($klantId);
        return [
            'pakketId'   => null,
            'geenPakket' => true,
            'klant'      => [
                'id'            => $klantId,
                'nummer'        => sprintf('K-%03d', $klantId),
                'naam'          => $k['Gezinsnaam'],
                'adres'         => $k['Adres'] . ', ' . $k['Plaats'],
                'gezinsgrootte' => Klant::gezinsgrootte($klantId),
            ],
            'inhoud' => [],
            'wensen' => [],
        ];
    }
}
