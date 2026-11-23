<?php
declare(strict_types=1);
/**
 * Dashboard — US-WRK-02, US-WRK-03, US-VRD-03, US-VRD-12.
 *
 * Iedereen die is ingelogd heeft een dashboard, maar niet hetzelfde: het
 * bestaat uit blokken die per permissie worden opgebouwd. Een
 * magazijnmedewerker krijgt zo de voorraad en de leveringen te zien, een
 * vrijwilliger de pakketten van deze week, en de directie alles.
 * Wat iemand niet mag inzien wordt hier weggelaten — niet pas in de browser.
 */

$g   = vereis_login();
$pdo = db();

$maandag   = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$thtDagen  = (int) instelling('tht_waarschuwing_dagen', '3');

$kaarten = [];     // de cijferblokken bovenaan
$blokken = [];     // de lijsten en grafieken daaronder
$taken   = [];     // "dit staat er voor jou open"

/* ---------------------------------------------- magazijn: voorraad
   Een vrijwilliger mag de voorraad corrigeren tijdens het samenstellen, maar
   het magazijn is niet zijn werk. Hij ziet daarom alleen de houdbaarheid —
   dat bepaalt immers wat hij wel of niet in een pakket mag doen. */
$magMagazijn = mag('product_beheren');
if ($magMagazijn || mag('voorraad_corrigeren')) {
    $totaal  = (int) $pdo->query("SELECT COALESCE(SUM(Aantal_voorraad),0) FROM Product")->fetchColumn();
    $soorten = (int) $pdo->query("SELECT COUNT(*) FROM Product")->fetchColumn();

    $q = $pdo->prepare("
        SELECT idProduct, Productnaam, Houdbaar_tot, Locatie, Aantal_voorraad
          FROM Product
         WHERE Houdbaar_tot IS NOT NULL AND Houdbaar_tot >= CURDATE()
           AND Houdbaar_tot <= CURDATE() + INTERVAL ? DAY
      ORDER BY Houdbaar_tot LIMIT 6
    ");
    $q->execute([$thtDagen]);
    $bijna = $q->fetchAll();

    $verlopen = $pdo->query("
        SELECT idProduct, Productnaam, Houdbaar_tot, Locatie, Aantal_voorraad
          FROM Product WHERE Houdbaar_tot IS NOT NULL AND Houdbaar_tot < CURDATE()
      ORDER BY Houdbaar_tot LIMIT 6
    ")->fetchAll();

    $onderMin = $pdo->query("
        SELECT idProduct, Productnaam, Aantal_voorraad, Min_voorraad, Locatie
          FROM Product WHERE Aantal_voorraad < Min_voorraad
      ORDER BY (Min_voorraad - Aantal_voorraad) DESC LIMIT 6
    ")->fetchAll();

    $aantalBijna    = (int) $pdo->query("
        SELECT COUNT(*) FROM Product WHERE Houdbaar_tot IS NOT NULL
           AND Houdbaar_tot >= CURDATE() AND Houdbaar_tot <= CURDATE() + INTERVAL $thtDagen DAY
    ")->fetchColumn();
    $aantalVerlopen = (int) $pdo->query("
        SELECT COUNT(*) FROM Product WHERE Houdbaar_tot IS NOT NULL AND Houdbaar_tot < CURDATE()
    ")->fetchColumn();
    $aantalOnderMin = (int) $pdo->query("
        SELECT COUNT(*) FROM Product WHERE Aantal_voorraad < Min_voorraad
    ")->fetchColumn();

    if ($magMagazijn) {
        $kaarten[] = ['sleutel' => 'voorraad', 'kleur' => 'groen', 'label' => 'Voorraad totaal',
                      'waarde' => $totaal, 'voet' => 'stuks in magazijn, verdeeld over ' . $soorten . ' producten'];
        $kaarten[] = ['sleutel' => 'onderMin', 'kleur' => $aantalOnderMin > 0 ? 'amber' : '',
                      'label' => 'Onder minimumvoorraad', 'waarde' => $aantalOnderMin,
                      'voet' => $aantalOnderMin ? 'producten die bijbesteld moeten worden' : 'alles is op peil',
                      'link' => 'producten.html'];
    }
    $kaarten[] = ['sleutel' => 'tht', 'kleur' => $aantalVerlopen > 0 ? 'rood' : 'amber',
                  'label' => 'Bijna over de THT', 'waarde' => $aantalBijna,
                  'voet' => 'binnen ' . $thtDagen . ' dagen · ' . $aantalVerlopen . ' al over de datum',
                  'link' => 'producten.html'];

    if ($magMagazijn) {
    $blokken[] = ['soort' => 'lijst', 'titel' => 'Onder de minimumvoorraad', 'tel' => 'bijbestellen',
        'leeg' => 'Elke voorraad zit boven het minimum.',
        'rijen' => array_map(static fn(array $p): array => [
            'naam'  => $p['Productnaam'],
            'onder' => $p['Locatie'] ?: 'magazijn',
            'waarde'=> $p['Aantal_voorraad'] . ' / ' . $p['Min_voorraad'] . ' st.',
            'rechts'=> 'minimum',
            'kleur' => 'amber',
        ], $onderMin)];
    }

    $thtRijen = array_merge(
        array_map(static fn(array $p): array => [
            'naam'  => $p['Productnaam'],
            'onder' => $p['Locatie'] ?: 'magazijn',
            'waarde'=> $p['Aantal_voorraad'] . ' st.',
            'rechts'=> 'over de datum',
            'kleur' => 'rood',
            'datum' => $p['Houdbaar_tot'],
        ], $verlopen),
        array_map(static fn(array $p): array => [
            'naam'  => $p['Productnaam'],
            'onder' => $p['Locatie'] ?: 'magazijn',
            'waarde'=> $p['Aantal_voorraad'] . ' st.',
            'rechts'=> 'THT',
            'kleur' => 'amber',
            'datum' => $p['Houdbaar_tot'],
        ], $bijna)
    );
    $blokken[] = ['soort' => 'lijst', 'titel' => 'Let op de houdbaarheid', 'tel' => 'eerst gebruiken',
        'leeg' => 'Geen enkel product nadert de THT.', 'rijen' => array_slice($thtRijen, 0, 6)];

    if ($aantalVerlopen > 0 && $magMagazijn) {
        $taken[] = ['tekst' => $aantalVerlopen . ' product' . ($aantalVerlopen === 1 ? '' : 'en')
                             . ' over de THT: afvoeren zodat ' . ($aantalVerlopen === 1 ? 'het' : 'ze')
                             . ' niet in een pakket beland' . ($aantalVerlopen === 1 ? 't' : 'en'),
                    'link' => 'producten.html', 'kleur' => 'rood'];
    }
    if ($aantalOnderMin > 0 && $magMagazijn) {
        $taken[] = ['tekst' => $aantalOnderMin . ' product' . ($aantalOnderMin === 1 ? '' : 'en')
                             . ' onder de minimumvoorraad', 'link' => 'producten.html', 'kleur' => 'amber'];
    }
}

/* ---------------------------------------------- magazijn: leveringen */
if (mag('levering_boeken') || mag('leverancier_beheren')) {
    $verwacht = $pdo->query("
        SELECT l.Bedrijfsnaam, l.Volgende_leverdatum, l.Volgende_levertijd, l.Contactpersoon
          FROM Leverancier l
         WHERE l.Actief = 1 AND l.Volgende_leverdatum IS NOT NULL
           AND l.Volgende_leverdatum >= CURDATE()
      ORDER BY l.Volgende_leverdatum, l.Volgende_levertijd LIMIT 5
    ")->fetchAll();

    $recent = $pdo->query("
        SELECT v.idLevering, v.Leverdatum, l.Bedrijfsnaam,
               COALESCE(SUM(lp.Aantal), 0) AS Aantal,
               (SELECT p.Productnaam FROM Levering_has_Product x
                  JOIN Product p ON p.idProduct = x.Product_idProduct
                 WHERE x.Levering_idLevering = v.idLevering
              ORDER BY x.Aantal DESC LIMIT 1) AS Grootste
          FROM Levering v
          JOIN Leverancier l ON l.idLeverancier = v.Leverancier_idLeverancier
     LEFT JOIN Levering_has_Product lp ON lp.Levering_idLevering = v.idLevering
      GROUP BY v.idLevering
      ORDER BY v.Leverdatum DESC, v.idLevering DESC LIMIT 5
    ")->fetchAll();

    if ($verwacht) {
        $eerste = $verwacht[0];
        $kaarten[] = ['sleutel' => 'levering', 'kleur' => '', 'label' => 'Eerstvolgende levering',
                      'tekst' => $eerste['Bedrijfsnaam'],
                      'voet'  => 'verwacht op '
                               . date('d-m-Y', strtotime((string) $eerste['Volgende_leverdatum']))
                               . ($eerste['Volgende_levertijd'] ? ' om ' . substr((string) $eerste['Volgende_levertijd'], 0, 5) : ''),
                      'link' => 'leveranciers.html'];
    }

    $blokken[] = ['soort' => 'lijst', 'titel' => 'Verwachte leveringen', 'tel' => 'op volgorde van datum',
        'leeg' => 'Er staat geen levering gepland.',
        'rijen' => array_map(static fn(array $l): array => [
            'naam'  => $l['Bedrijfsnaam'],
            'onder' => $l['Contactpersoon'] ?: 'geen contactpersoon',
            'waarde'=> $l['Volgende_levertijd'] ? substr((string) $l['Volgende_levertijd'], 0, 5) . ' uur' : '',
            'datum' => $l['Volgende_leverdatum'],
        ], $verwacht)];

    $blokken[] = ['soort' => 'lijst', 'titel' => 'Recente leveringen', 'tel' => 'laatste 5',
        'leeg' => 'Nog geen leveringen geboekt.',
        'rijen' => array_map(static fn(array $l): array => [
            'naam'  => $l['Bedrijfsnaam'],
            'onder' => $l['Grootste'] ?: 'levering',
            'waarde'=> (int) $l['Aantal'] . ' st.',
            'datum' => $l['Leverdatum'],
        ], $recent)];
}

/* ---------------------------------------------- vrijwilliger: pakketten */
if (mag('pakket_beheren')) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM Pakket WHERE Samenstellingsdatum >= ?");
    $q->execute([$maandag]);
    $pakkettenWeek = (int) $q->fetchColumn();

    $q = $pdo->prepare("SELECT COUNT(*) FROM Pakket WHERE Samenstellingsdatum >= ? AND Status = 'Klaar'");
    $q->execute([$maandag]);
    $klaarWeek = (int) $q->fetchColumn();

    // Geaccepteerde gezinnen die deze week nog niets hebben (US-PAK-06)
    $q = $pdo->prepare("
        SELECT k.idKlant, k.Gezinsnaam,
               (SELECT COUNT(*) FROM Gezinslid gl WHERE gl.Klant_idKlant = k.idKlant) AS Grootte,
               (SELECT MAX(p.Uitgiftedatum) FROM Pakket p
                 WHERE p.Klant_idKlant = k.idKlant AND p.Status = 'Uitgegeven') AS Laatste
          FROM Klant k
         WHERE k.Geaccepteerd = 1
           AND NOT EXISTS (SELECT 1 FROM Pakket p
                            WHERE p.Klant_idKlant = k.idKlant AND p.Samenstellingsdatum >= ?)
      ORDER BY Laatste IS NULL DESC, Laatste, k.Gezinsnaam LIMIT 6
    ");
    $q->execute([$maandag]);
    $teDoen = $q->fetchAll();

    $q = $pdo->prepare("
        SELECT COUNT(*) FROM Klant k
         WHERE k.Geaccepteerd = 1
           AND NOT EXISTS (SELECT 1 FROM Pakket p
                            WHERE p.Klant_idKlant = k.idKlant AND p.Samenstellingsdatum >= ?)
    ");
    $q->execute([$maandag]);
    $aantalTeDoen = (int) $q->fetchColumn();

    // eigen concepten: werk dat deze gebruiker nog moet afmaken
    $q = $pdo->prepare("
        SELECT p.idPakket, k.Gezinsnaam, p.Samenstellingsdatum,
               (SELECT COALESCE(SUM(pp.Aantal),0) FROM Product_has_Pakket pp
                 WHERE pp.Pakket_idPakket = p.idPakket) AS Stuks
          FROM Pakket p JOIN Klant k ON k.idKlant = p.Klant_idKlant
         WHERE p.Status = 'Concept' AND p.Samengesteld_door = ?
      ORDER BY p.Samenstellingsdatum DESC LIMIT 6
    ");
    $q->execute([(int) $g['idGebruiker']]);
    $concepten = $q->fetchAll();

    $kaarten[] = ['sleutel' => 'pakketten', 'kleur' => 'groen', 'label' => 'Pakketten deze week',
                  'waarde' => $pakkettenWeek, 'voet' => $klaarWeek . ' klaar voor de uitgifte',
                  'link' => 'pakketten.html'];
    $kaarten[] = ['sleutel' => 'teDoen', 'kleur' => $aantalTeDoen > 0 ? 'amber' : '',
                  'label' => 'Nog samen te stellen', 'waarde' => $aantalTeDoen,
                  'voet' => $aantalTeDoen ? 'gezinnen zonder pakket deze week' : 'elk gezin heeft een pakket',
                  'link' => 'pakketten.html'];

    $blokken[] = ['soort' => 'lijst', 'titel' => 'Gezinnen zonder pakket deze week',
        'tel' => 'langst geleden bovenaan', 'leeg' => 'Elk geaccepteerd gezin heeft deze week een pakket.',
        'rijen' => array_map(static fn(array $k): array => [
            'naam'  => $k['Gezinsnaam'],
            'onder' => (int) $k['Grootte'] . ((int) $k['Grootte'] === 1 ? ' persoon' : ' personen'),
            'rechts'=> $k['Laatste'] ? '' : 'nog nooit',
            'datum' => $k['Laatste'],
            'link'  => 'pakketten.html?klant=' . (int) $k['idKlant'],
        ], $teDoen)];

    if ($concepten) {
        $blokken[] = ['soort' => 'lijst', 'titel' => 'Jouw concepten', 'tel' => 'nog niet klaargemeld',
            'leeg' => 'Je hebt geen openstaande concepten.',
            'rijen' => array_map(static fn(array $p): array => [
                'naam'  => $p['Gezinsnaam'],
                'onder' => (int) $p['Stuks'] . ' stuks in het pakket',
                'rechts'=> 'concept',
                'kleur' => 'amber',
                'datum' => $p['Samenstellingsdatum'],
                'link'  => 'pakketten.html?pakket=' . (int) $p['idPakket'],
            ], $concepten)];
        $taken[] = ['tekst' => count($concepten) . ' eigen concept' . (count($concepten) === 1 ? '' : 'en')
                             . ' nog afmaken', 'link' => 'pakketten.html', 'kleur' => 'amber'];
    }
    if ($aantalTeDoen > 0) {
        $taken[] = ['tekst' => $aantalTeDoen . ' gezin' . ($aantalTeDoen === 1 ? '' : 'nen')
                             . ' wacht' . ($aantalTeDoen === 1 ? '' : 'en') . ' nog op een pakket deze week',
                    'link' => 'pakketten.html', 'kleur' => 'amber'];
    }
}

/* ---------------------------------------------- vrijwilliger: uitgifte */
if (mag('pakket_uitgeven')) {
    $klaar = $pdo->query("
        SELECT p.idPakket, k.Gezinsnaam, p.Samenstellingsdatum,
               (SELECT COALESCE(SUM(pp.Aantal),0) FROM Product_has_Pakket pp
                 WHERE pp.Pakket_idPakket = p.idPakket) AS Stuks
          FROM Pakket p JOIN Klant k ON k.idKlant = p.Klant_idKlant
         WHERE p.Status = 'Klaar'
      ORDER BY p.Samenstellingsdatum LIMIT 6
    ")->fetchAll();

    $aantalKlaar = (int) $pdo->query("SELECT COUNT(*) FROM Pakket WHERE Status = 'Klaar'")->fetchColumn();

    $kaarten[] = ['sleutel' => 'klaar', 'kleur' => '', 'label' => 'Klaar voor uitgifte',
                  'waarde' => $aantalKlaar, 'voet' => 'pakketten die opgehaald kunnen worden',
                  'link' => 'uitgifte.html'];

    $blokken[] = ['soort' => 'lijst', 'titel' => 'Klaar voor uitgifte', 'tel' => 'oudste eerst',
        'leeg' => 'Er staat geen pakket klaar.',
        'rijen' => array_map(static fn(array $p): array => [
            'naam'  => $p['Gezinsnaam'],
            'onder' => (int) $p['Stuks'] . ' stuks',
            'rechts'=> 'klaar',
            'kleur' => 'groen',
            'datum' => $p['Samenstellingsdatum'],
            'link'  => 'uitgifte.html',
        ], $klaar)];

    if ($aantalKlaar > 0) {
        $taken[] = ['tekst' => $aantalKlaar . ' pakket' . ($aantalKlaar === 1 ? '' : 'ten')
                             . ' klaar om uit te geven', 'link' => 'uitgifte.html', 'kleur' => 'groen'];
    }
}

/* ---------------------------------------------- klanten (US-SEC-02) */
if (mag('klant_inzien')) {
    $actief   = (int) $pdo->query("SELECT COUNT(*) FROM Klant WHERE Geaccepteerd = 1")->fetchColumn();
    $wachtend = (int) $pdo->query("SELECT COUNT(*) FROM Klant WHERE Geaccepteerd IS NULL")->fetchColumn();

    $kaarten[] = ['sleutel' => 'klanten', 'kleur' => '', 'label' => 'Klanten actief', 'waarde' => $actief,
                  'voet' => 'gezinnen geregistreerd'
                          . ($wachtend ? ' · ' . $wachtend . ' wacht' . ($wachtend === 1 ? '' : 'en') . ' op beoordeling' : ''),
                  'link' => 'klanten.html'];

    // Alleen wie klanten mag beoordelen krijgt de wachtrij er ook bij.
    if (mag('klant_beheren') && $wachtend > 0) {
        $wacht = $pdo->query("
            SELECT idKlant, Gezinsnaam, Aanmelddatum,
                   (SELECT COUNT(*) FROM Gezinslid gl WHERE gl.Klant_idKlant = Klant.idKlant) AS Grootte
              FROM Klant WHERE Geaccepteerd IS NULL ORDER BY Aanmelddatum LIMIT 6
        ")->fetchAll();

        $blokken[] = ['soort' => 'lijst', 'titel' => 'Aanvragen die wachten op beoordeling',
            'tel' => 'oudste eerst', 'leeg' => 'Geen openstaande aanvragen.',
            'rijen' => array_map(static fn(array $k): array => [
                'naam'  => $k['Gezinsnaam'],
                'onder' => (int) $k['Grootte'] . ((int) $k['Grootte'] === 1 ? ' persoon' : ' personen'),
                'rechts'=> 'aangemeld',
                'kleur' => 'amber',
                'datum' => $k['Aanmelddatum'],
                'link'  => 'klanten.html',
            ], $wacht)];

        $taken[] = ['tekst' => $wachtend . ' aanvra' . ($wachtend === 1 ? 'ag' : 'gen')
                             . ' wacht' . ($wachtend === 1 ? '' : 'en') . ' op beoordeling',
                    'link' => 'klanten.html', 'kleur' => 'amber'];
    }
}

/* ---------------------------------------------- directie: cijfers */
if (mag('rapportage_export')) {
    $weken = $pdo->query("
        SELECT YEARWEEK(Uitgiftedatum, 3) AS Jw, WEEK(Uitgiftedatum, 3) AS Weeknr, COUNT(*) AS Aantal
          FROM Pakket
         WHERE Status = 'Uitgegeven' AND Uitgiftedatum >= CURDATE() - INTERVAL 35 DAY
      GROUP BY Jw, Weeknr ORDER BY Jw
    ")->fetchAll();

    $blokken[] = ['soort' => 'grafiek', 'titel' => 'Wekelijkse uitgifte pakketten', 'tel' => 'laatste 5 weken',
        'weken' => array_map(static fn(array $w): array => [
            'label'  => 'W' . (int) $w['Weeknr'],
            'aantal' => (int) $w['Aantal'],
        ], $weken)];
}

/* ---------------------------------------------- accountbeheer */
if (mag('gebruiker_beheren')) {
    $inactief = (int) $pdo->query("SELECT COUNT(*) FROM Gebruiker WHERE Actief = 0")->fetchColumn();
    $actieveG = (int) $pdo->query("SELECT COUNT(*) FROM Gebruiker WHERE Actief = 1")->fetchColumn();
    $kaarten[] = ['sleutel' => 'gebruikers', 'kleur' => '', 'label' => 'Medewerkers', 'waarde' => $actieveG,
                  'voet' => $inactief ? $inactief . ' account' . ($inactief === 1 ? '' : 's') . ' geblokkeerd'
                                      : 'alle accounts actief',
                  'link' => 'gebruikers.html'];
}

/* ---------------------------------------------- wat dit dashboard is */
$introPerRol = [
    'Directie'           => 'Het volledige beeld: voorraad, pakketten, klanten en de cijfers.',
    'Magazijnmedewerker' => 'Jouw magazijn: wat er bijna op is, wat bijna over de datum is en wat er binnenkomt.',
    'Vrijwilliger'       => 'Jouw werk van deze week: welke gezinnen nog een pakket nodig hebben en wat klaarstaat.',
    'Administratie'      => 'De administratie: klantaanvragen, accounts en de cijfers.',
];

json_out([
    'gebruiker' => gebruiker_publiek($g),
    'intro'     => $introPerRol[$g['RolNaam']] ?? 'Jouw overzicht bij Voedselbank Maaskantje.',
    'kaarten'   => $kaarten,
    'blokken'   => $blokken,
    'taken'     => $taken,
]);
