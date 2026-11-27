<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use App\Services\Settings;
use App\Models\Gebruiker;
use App\Models\Klant;
use App\Models\Leverancier;
use App\Models\Pakket;
use App\Models\Product;
use App\Models\Rapportage;
use App\Services\Weekcyclus;

/**
 * Dashboard per rol — US-WRK-02, US-WRK-03, US-VRD-03, US-VRD-12.
 *
 * Het dashboard bestaat uit blokken die per permissie worden toegevoegd.
 * Een magazijnmedewerker ziet zo voorraad en leveringen, een vrijwilliger de
 * pakketten van deze week en de directie alles. Wat iemand niet mag zien,
 * wordt hier al weggelaten — niet pas in de browser.
 */
final class DashboardController
{
    private const INTRO = [
        'Directie'           => 'Het volledige beeld: voorraad, pakketten, klanten en de cijfers.',
        'Magazijnmedewerker' => 'Jouw magazijn: wat er bijna op is, wat bijna over de datum is en wat er binnenkomt.',
        'Vrijwilliger'       => 'Jouw werk van deze week: welke gezinnen nog een pakket nodig hebben en wat klaarstaat.',
        'Administratie'      => 'De administratie: klantaanvragen, accounts en de cijfers.',
    ];

    /** cijferkaarten bovenaan */
    private array $kaarten = [];
    /** lijsten en grafieken */
    private array $blokken = [];
    /** "dit staat er voor jou open" */
    private array $taken = [];

    /** GET /dashboard */
    public function index(Request $request): array
    {
        $g = AuthService::user();

        if (AuthService::can('product_beheren') || AuthService::can('voorraad_corrigeren')) { $this->voorraad(); }
        if (AuthService::can('levering_boeken') || AuthService::can('leverancier_beheren')) { $this->leveringen(); }
        if (AuthService::can('pakket_beheren'))    { $this->pakketten(); }
        if (AuthService::can('pakket_uitgeven'))   { $this->uitgifte(); }
        if (AuthService::can('klant_inzien'))      { $this->klanten(); }
        if (AuthService::can('rapportage_export')) { $this->cijfers(); }
        if (AuthService::can('gebruiker_beheren')) { $this->gebruikers(); }

        return [
            'gebruiker' => AuthService::publicProfile($g),
            'intro'     => self::INTRO[$g['RolNaam']] ?? 'Jouw overzicht bij Voedselbank Maaskantje.',
            'kaarten'   => $this->kaarten,
            'blokken'   => $this->blokken,
            'taken'     => $this->taken,
        ];
    }

    /**
     * Magazijn. Een vrijwilliger mag de voorraad corrigeren, maar het magazijn is
     * niet zijn werk: hij ziet alleen de houdbaarheid, want die bepaalt wat er in
     * een pakket mag.
     */
    private function voorraad(): void
    {
        $magazijn = AuthService::can('product_beheren');
        $dagen = Settings::int('tht_waarschuwing_dagen', 3);
        $aantalBijna = Product::aantalBijnaOverDatum($dagen);
        $aantalVerlopen = Product::aantalOverDatum();
        $aantalOnderMin = Product::aantalOnderMinimum();

        if ($magazijn) {
            $this->kaarten[] = ['sleutel' => 'voorraad', 'kleur' => 'groen', 'label' => 'Voorraad totaal',
                'waarde' => Product::totaleVoorraad(),
                'voet' => 'stuks in magazijn, verdeeld over ' . Product::aantalSoorten() . ' producten'];
            $this->kaarten[] = ['sleutel' => 'onderMin', 'kleur' => $aantalOnderMin > 0 ? 'amber' : '',
                'label' => 'Onder minimumvoorraad', 'waarde' => $aantalOnderMin,
                'voet' => $aantalOnderMin ? 'producten die bijbesteld moeten worden' : 'alles is op peil',
                'link' => '/producten'];
        }
        $this->kaarten[] = ['sleutel' => 'tht', 'kleur' => $aantalVerlopen > 0 ? 'rood' : 'amber',
            'label' => 'Bijna over de THT', 'waarde' => $aantalBijna,
            'voet' => 'binnen ' . $dagen . ' dagen · ' . $aantalVerlopen . ' al over de datum', 'link' => '/producten'];

        if ($magazijn) {
            $this->blokken[] = $this->lijst('Onder de minimumvoorraad', 'bijbestellen', 'Elke voorraad zit boven het minimum.',
                array_map(static fn(array $p): array => [
                    'naam' => $p['Productnaam'], 'onder' => $p['Locatie'] ?: 'magazijn',
                    'waarde' => $p['Aantal_voorraad'] . ' / ' . $p['Min_voorraad'] . ' st.', 'rechts' => 'minimum', 'kleur' => 'amber',
                ], Product::onderMinimum(6)));
        }

        $thtRij = static fn(string $rechts, string $kleur) => static fn(array $p): array => [
            'naam' => $p['Productnaam'], 'onder' => $p['Locatie'] ?: 'magazijn', 'waarde' => $p['Aantal_voorraad'] . ' st.',
            'rechts' => $rechts, 'kleur' => $kleur, 'datum' => $p['Houdbaar_tot'],
        ];
        $rijen = array_merge(
            array_map($thtRij('over de datum', 'rood'), Product::overDatum(6)),
            array_map($thtRij('THT', 'amber'), Product::bijnaOverDatum($dagen, 6))
        );
        $this->blokken[] = $this->lijst('Let op de houdbaarheid', 'eerst gebruiken', 'Geen enkel product nadert de THT.', array_slice($rijen, 0, 6));

        if ($magazijn && $aantalVerlopen > 0) {
            $een = $aantalVerlopen === 1;
            $this->taken[] = ['tekst' => $aantalVerlopen . ' product' . ($een ? '' : 'en') . ' over de THT: afvoeren zodat '
                . ($een ? 'het niet in een pakket belandt' : 'ze niet in een pakket belanden'), 'link' => '/producten', 'kleur' => 'rood'];
        }
        if ($magazijn && $aantalOnderMin > 0) {
            $this->taken[] = ['tekst' => $aantalOnderMin . ' product' . ($aantalOnderMin === 1 ? '' : 'en') . ' onder de minimumvoorraad',
                'link' => '/producten', 'kleur' => 'amber'];
        }
    }

    private function leveringen(): void
    {
        $verwacht = Leverancier::verwacht(5);
        if ($verwacht) {
            $e = $verwacht[0];
            $this->kaarten[] = ['sleutel' => 'levering', 'kleur' => '', 'label' => 'Eerstvolgende levering', 'tekst' => $e['Bedrijfsnaam'],
                'voet' => 'verwacht op ' . date('d-m-Y', strtotime((string) $e['Volgende_leverdatum']))
                        . ($e['Volgende_levertijd'] ? ' om ' . substr((string) $e['Volgende_levertijd'], 0, 5) : ''),
                'link' => '/leveranciers'];
        }

        $this->blokken[] = $this->lijst('Verwachte leveringen', 'op volgorde van datum', 'Er staat geen levering gepland.',
            array_map(static fn(array $l): array => [
                'naam' => $l['Bedrijfsnaam'], 'onder' => $l['Contactpersoon'] ?: 'geen contactpersoon',
                'waarde' => $l['Volgende_levertijd'] ? substr((string) $l['Volgende_levertijd'], 0, 5) . ' uur' : '',
                'datum' => $l['Volgende_leverdatum'],
            ], $verwacht));

        $this->blokken[] = $this->lijst('Recente leveringen', 'laatste 5', 'Nog geen leveringen geboekt.',
            array_map(static fn(array $l): array => [
                'naam' => $l['Bedrijfsnaam'], 'onder' => $l['Grootste'] ?: 'levering',
                'waarde' => (int) $l['Aantal'] . ' st.', 'datum' => $l['Leverdatum'],
            ], Leverancier::recenteLeveringen(5)));
    }

    private function pakketten(): void
    {
        $maandag = Weekcyclus::weekStart();
        $zonder = Pakket::gezinnenZonderPakket($maandag);
        $aantalZonder = count($zonder);
        $concepten = Pakket::conceptenVan(AuthService::id(), 6);

        $this->kaarten[] = ['sleutel' => 'pakketten', 'kleur' => 'groen', 'label' => 'Pakketten deze week',
            'waarde' => Pakket::aantalVanaf($maandag), 'voet' => Pakket::aantalVanaf($maandag, 'Klaar') . ' klaar voor de uitgifte',
            'link' => '/pakketten'];
        $this->kaarten[] = ['sleutel' => 'teDoen', 'kleur' => $aantalZonder > 0 ? 'amber' : '', 'label' => 'Nog samen te stellen',
            'waarde' => $aantalZonder, 'voet' => $aantalZonder ? 'gezinnen zonder pakket deze week' : 'elk gezin heeft een pakket',
            'link' => '/pakketten'];

        $this->blokken[] = $this->lijst('Gezinnen zonder pakket deze week', 'langst geleden bovenaan',
            'Elk geaccepteerd gezin heeft deze week een pakket.',
            array_map(static fn(array $k): array => [
                'naam' => $k['Gezinsnaam'], 'onder' => (int) $k['Grootte'] . ((int) $k['Grootte'] === 1 ? ' persoon' : ' personen'),
                'rechts' => $k['Laatste'] ? '' : 'nog nooit', 'datum' => $k['Laatste'],
                'link' => '/pakketten?klant=' . (int) $k['idKlant'],
            ], array_slice($zonder, 0, 6)));

        if ($concepten) {
            $this->blokken[] = $this->lijst('Jouw concepten', 'nog niet klaargemeld', 'Je hebt geen openstaande concepten.',
                array_map(static fn(array $p): array => [
                    'naam' => $p['Gezinsnaam'], 'onder' => (int) $p['Stuks'] . ' stuks in het pakket', 'rechts' => 'concept',
                    'kleur' => 'amber', 'datum' => $p['Samenstellingsdatum'], 'link' => '/pakketten?pakket=' . (int) $p['idPakket'],
                ], $concepten));
            $this->taken[] = ['tekst' => count($concepten) . ' eigen concept' . (count($concepten) === 1 ? '' : 'en') . ' nog afmaken',
                'link' => '/pakketten', 'kleur' => 'amber'];
        }
        if ($aantalZonder > 0) {
            $een = $aantalZonder === 1;
            $this->taken[] = ['tekst' => $aantalZonder . ' gezin' . ($een ? '' : 'nen') . ' wacht' . ($een ? '' : 'en')
                . ' nog op een pakket deze week', 'link' => '/pakketten', 'kleur' => 'amber'];
        }
    }

    private function uitgifte(): void
    {
        $aantal = Pakket::aantalMetStatus('Klaar');
        $this->kaarten[] = ['sleutel' => 'klaar', 'kleur' => '', 'label' => 'Klaar voor uitgifte', 'waarde' => $aantal,
            'voet' => 'pakketten die opgehaald kunnen worden', 'link' => '/uitgifte'];

        $this->blokken[] = $this->lijst('Klaar voor uitgifte', 'oudste eerst', 'Er staat geen pakket klaar.',
            array_map(static fn(array $p): array => [
                'naam' => $p['Gezinsnaam'], 'onder' => (int) $p['Stuks'] . ' stuks', 'rechts' => 'klaar', 'kleur' => 'groen',
                'datum' => $p['Samenstellingsdatum'], 'link' => '/uitgifte',
            ], Pakket::klaarVoorUitgifte(6)));

        if ($aantal > 0) {
            $this->taken[] = ['tekst' => $aantal . ' pakket' . ($aantal === 1 ? '' : 'ten') . ' klaar om uit te geven',
                'link' => '/uitgifte', 'kleur' => 'groen'];
        }
    }

    /** Klanten (US-SEC-02): alleen wie mag beoordelen ziet de wachtrij met aanvragen. */
    private function klanten(): void
    {
        $actief = Klant::aantalMetStatus(true);
        $wachtend = Klant::aantalMetStatus(null);

        $this->kaarten[] = ['sleutel' => 'klanten', 'kleur' => '', 'label' => 'Klanten actief', 'waarde' => $actief,
            'voet' => 'gezinnen geregistreerd' . ($wachtend ? ' · ' . $wachtend . ' wacht' . ($wachtend === 1 ? '' : 'en') . ' op beoordeling' : ''),
            'link' => '/klanten'];

        if (AuthService::can('klant_beheren') && $wachtend > 0) {
            $this->blokken[] = $this->lijst('Aanvragen die wachten op beoordeling', 'oudste eerst', 'Geen openstaande aanvragen.',
                array_map(static fn(array $k): array => [
                    'naam' => $k['Gezinsnaam'], 'onder' => (int) $k['Grootte'] . ((int) $k['Grootte'] === 1 ? ' persoon' : ' personen'),
                    'rechts' => 'aangemeld', 'kleur' => 'amber', 'datum' => $k['Aanmelddatum'], 'link' => '/klanten',
                ], Klant::wachtendOpBeoordeling(6)));
            $this->taken[] = ['tekst' => $wachtend . ' aanvra' . ($wachtend === 1 ? 'ag' : 'gen') . ' wacht' . ($wachtend === 1 ? '' : 'en')
                . ' op beoordeling', 'link' => '/klanten', 'kleur' => 'amber'];
        }
    }

    private function cijfers(): void
    {
        $this->blokken[] = ['soort' => 'grafiek', 'titel' => 'Wekelijkse uitgifte pakketten', 'tel' => 'laatste 5 weken',
            'weken' => array_map(static fn(array $w): array => ['label' => 'W' . (int) $w['Weeknr'], 'aantal' => (int) $w['Aantal']],
                Rapportage::perWeek())];
    }

    private function gebruikers(): void
    {
        $geblokkeerd = Gebruiker::aantal(false);
        $this->kaarten[] = ['sleutel' => 'gebruikers', 'kleur' => '', 'label' => 'Medewerkers', 'waarde' => Gebruiker::aantal(true),
            'voet' => $geblokkeerd ? $geblokkeerd . ' account' . ($geblokkeerd === 1 ? '' : 's') . ' geblokkeerd' : 'alle accounts actief',
            'link' => '/gebruikers'];
    }

    private function lijst(string $titel, string $tel, string $leeg, array $rijen): array
    {
        return ['soort' => 'lijst', 'titel' => $titel, 'tel' => $tel, 'leeg' => $leeg, 'rijen' => $rijen];
    }
}
