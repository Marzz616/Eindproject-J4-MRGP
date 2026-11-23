<?php
declare(strict_types=1);
/**
 * Rapportages & Statistieken — US-RAP-01 t/m US-RAP-06.
 * Alleen voor rollen met het recht rapportage_export (Directie, Administratie).
 *
 *   GET ?actie=overzicht            alle cijfers voor het scherm
 *   GET ?actie=export&rapport=...   dezelfde cijfers als CSV-download (US-RAP-06)
 *   GET ?actie=maand&soort=..&maand=&jaar=  de twee maandoverzichten (RAP-1/RAP-2)
 */

vereis_permissie('rapportage_export');
$actie = $_GET['actie'] ?? 'overzicht';

/* ---------------- maandoverzichten (RAP-1, RAP-2, RAP-3) ----------------
   Twee vaste overzichten uit de casus, te filteren op maand en jaar en
   af te drukken. Ze worden apart opgehaald, zodat het hoofdscherm snel blijft. */
if ($actie === 'maand') {
    $maand = (int) ($_GET['maand'] ?? date('n'));
    $jaar  = (int) ($_GET['jaar']  ?? date('Y'));
    $soort = (string) ($_GET['soort'] ?? 'categorie');

    if ($maand < 1 || $maand > 12 || $jaar < 2000 || $jaar > 2100) {
        json_out(['fout' => 'Kies een geldige maand en een geldig jaar.'], 422);
    }

    // RAP-1: per productcategorie, aantal per product en per leverancier
    if ($soort === 'categorie') {
        $q = db()->prepare("
            SELECT c.Naam AS Categorie, p.Productnaam, l.Bedrijfsnaam AS Leverancier,
                   SUM(lp.Aantal) AS Geleverd
              FROM Levering v
              JOIN Levering_has_Product lp ON lp.Levering_idLevering = v.idLevering
              JOIN Product p   ON p.idProduct = lp.Product_idProduct
              JOIN Categorie c ON c.idCategorie = p.Categorie_idCategorie
              JOIN Leverancier l ON l.idLeverancier = v.Leverancier_idLeverancier
             WHERE MONTH(v.Leverdatum) = ? AND YEAR(v.Leverdatum) = ?
             GROUP BY c.idCategorie, p.idProduct, l.idLeverancier
             ORDER BY c.Naam, p.Productnaam, l.Bedrijfsnaam
        ");
        $q->execute([$maand, $jaar]);

        // wat er in diezelfde maand van dat product is uitgegeven
        $u = db()->prepare("
            SELECT p.idProduct, SUM(pp.Aantal) AS Uitgegeven
              FROM Pakket pk
              JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = pk.idPakket
              JOIN Product p ON p.idProduct = pp.Product_idProduct
             WHERE pk.Status = 'Uitgegeven'
               AND MONTH(pk.Uitgiftedatum) = ? AND YEAR(pk.Uitgiftedatum) = ?
             GROUP BY p.idProduct
        ");
        $u->execute([$maand, $jaar]);
        $uitPerProduct = [];
        foreach ($u->fetchAll() as $r) { $uitPerProduct[(int) $r['idProduct']] = (int) $r['Uitgegeven']; }

        $q2 = db()->prepare("
            SELECT p.idProduct, p.Productnaam FROM Product p
        ");
        $q2->execute();
        $naamPerId = [];
        foreach ($q2->fetchAll() as $r) { $naamPerId[$r['Productnaam']] = (int) $r['idProduct']; }

        $rijen = [];
        foreach ($q->fetchAll() as $r) {
            $pid = $naamPerId[$r['Productnaam']] ?? 0;
            $rijen[] = [
                'categorie'   => $r['Categorie'],
                'product'     => $r['Productnaam'],
                'leverancier' => $r['Leverancier'],
                'geleverd'    => (int) $r['Geleverd'],
                'uitgegeven'  => $uitPerProduct[$pid] ?? 0,
            ];
        }

        json_out([
            'soort' => 'categorie',
            'maand' => $maand, 'jaar' => $jaar,
            'rijen' => $rijen,
            'totaal' => array_sum(array_column($rijen, 'geleverd')),
        ]);
    }

    // RAP-2: per postcode, aantallen per productcategorie
    if ($soort === 'postcode') {
        $q = db()->prepare("
            SELECT k.Postcode, c.Naam AS Categorie,
                   SUM(pp.Aantal) AS Aantal,
                   COUNT(DISTINCT pk.idPakket) AS Pakketten,
                   COUNT(DISTINCT k.idKlant)   AS Gezinnen
              FROM Pakket pk
              JOIN Klant k ON k.idKlant = pk.Klant_idKlant
              JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = pk.idPakket
              JOIN Product p   ON p.idProduct = pp.Product_idProduct
              JOIN Categorie c ON c.idCategorie = p.Categorie_idCategorie
             WHERE pk.Status = 'Uitgegeven'
               AND MONTH(pk.Uitgiftedatum) = ? AND YEAR(pk.Uitgiftedatum) = ?
             GROUP BY k.Postcode, c.idCategorie
             ORDER BY k.Postcode, c.Naam
        ");
        $q->execute([$maand, $jaar]);

        $categorieen = db()->query("SELECT Naam FROM Categorie ORDER BY Naam")->fetchAll(PDO::FETCH_COLUMN);

        $perPostcode = [];
        foreach ($q->fetchAll() as $r) {
            $pc = $r['Postcode'];
            if (!isset($perPostcode[$pc])) {
                $perPostcode[$pc] = ['postcode' => $pc, 'pakketten' => 0, 'gezinnen' => 0, 'per' => [], 'totaal' => 0];
            }
            $perPostcode[$pc]['per'][$r['Categorie']] = (int) $r['Aantal'];
            $perPostcode[$pc]['totaal']   += (int) $r['Aantal'];
            $perPostcode[$pc]['pakketten'] = max($perPostcode[$pc]['pakketten'], (int) $r['Pakketten']);
            $perPostcode[$pc]['gezinnen']  = max($perPostcode[$pc]['gezinnen'],  (int) $r['Gezinnen']);
        }

        json_out([
            'soort' => 'postcode',
            'maand' => $maand, 'jaar' => $jaar,
            'categorieen' => $categorieen,
            'rijen' => array_values($perPostcode),
        ]);
    }

    json_out(['fout' => 'Onbekend maandoverzicht.'], 404);
}

/* ---------------- 1. uitgegeven pakketten ---------------- */
$ytd = (int) db()->query("
    SELECT COUNT(*) FROM Pakket
     WHERE Status = 'Uitgegeven' AND YEAR(Uitgiftedatum) = YEAR(CURDATE())
")->fetchColumn();

$ytdVorig = (int) db()->query("
    SELECT COUNT(*) FROM Pakket
     WHERE Status = 'Uitgegeven'
       AND YEAR(Uitgiftedatum) = YEAR(CURDATE()) - 1
       AND DAYOFYEAR(Uitgiftedatum) <= DAYOFYEAR(CURDATE())
")->fetchColumn();

$groei = $ytdVorig > 0 ? round(($ytd - $ytdVorig) / $ytdVorig * 100, 1) : null;

/* ---------------- 2. gemiddelde pakketwaarde (US-RAP-02) ---------------- */
$w = db()->query("
    SELECT COUNT(DISTINCT p.idPakket) AS Pakketten,
           COALESCE(SUM(pp.Aantal * pr.Waarde), 0) AS Waarde
      FROM Pakket p
      JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
      JOIN Product pr ON pr.idProduct = pp.Product_idProduct
     WHERE p.Status = 'Uitgegeven'
")->fetch();

$gemiddelde = (int) $w['Pakketten'] > 0 ? round((float) $w['Waarde'] / (int) $w['Pakketten'], 2) : 0.0;

/* ---------------- 3. wekelijkse uitgifte (US-RAP-01) ---------------- */
$weken = db()->query("
    SELECT WEEK(Uitgiftedatum, 3) AS Weeknr, COUNT(*) AS Aantal,
           MIN(Uitgiftedatum) AS Van, MAX(Uitgiftedatum) AS Tot
      FROM Pakket
     WHERE Status = 'Uitgegeven' AND Uitgiftedatum >= CURDATE() - INTERVAL 35 DAY
     GROUP BY YEARWEEK(Uitgiftedatum, 3), Weeknr
     ORDER BY YEARWEEK(Uitgiftedatum, 3)
")->fetchAll();

/* ---------------- 4. voorraadverloop 30 dagen (US-RAP-03) ----------------
   Er wordt geen dagelijkse momentopname bewaard, dus het verloop wordt
   teruggerekend vanaf de voorraad van vandaag: per dag erbij wat er is
   geleverd, eraf wat er in pakketten en in de afvoer is gegaan.          */
$vandaag = (int) db()->query("SELECT COALESCE(SUM(Aantal_voorraad),0) FROM Product")->fetchColumn();

$in = [];
foreach (db()->query("
    SELECT v.Leverdatum AS D, COALESCE(SUM(lp.Aantal),0) AS N
      FROM Levering v JOIN Levering_has_Product lp ON lp.Levering_idLevering = v.idLevering
     WHERE v.Leverdatum >= CURDATE() - INTERVAL 30 DAY GROUP BY D
")->fetchAll() as $r) { $in[$r['D']] = (int) $r['N']; }

$uitDag = [];
foreach (db()->query("
    SELECT p.Uitgiftedatum AS D, COALESCE(SUM(pp.Aantal),0) AS N
      FROM Pakket p JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
     WHERE p.Status = 'Uitgegeven' AND p.Uitgiftedatum >= CURDATE() - INTERVAL 30 DAY GROUP BY D
")->fetchAll() as $r) { $uitDag[$r['D']] = (int) $r['N']; }

foreach (db()->query("
    SELECT Datum AS D, SUM(Aantal) AS N FROM Afvoer
     WHERE Datum >= CURDATE() - INTERVAL 30 DAY GROUP BY D
")->fetchAll() as $r) { $uitDag[$r['D']] = ($uitDag[$r['D']] ?? 0) + (int) $r['N']; }

$verloop = [];
$stand   = $vandaag;
for ($i = 0; $i <= 30; $i++) {
    $dag = (new DateTimeImmutable("-{$i} days"))->format('Y-m-d');
    array_unshift($verloop, ['datum' => $dag, 'stand' => max(0, $stand)]);
    $stand = $stand - ($in[$dag] ?? 0) + ($uitDag[$dag] ?? 0);   // een dag terug
}

/* ---------------- 5. bestemming & verspilling (US-RAP-04) ---------------- */
$inPakket = (int) db()->query("
    SELECT COALESCE(SUM(pp.Aantal),0) FROM Pakket p
      JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
     WHERE p.Status = 'Uitgegeven'
")->fetchColumn();

$afvoer = [];
foreach (db()->query("SELECT Reden, SUM(Aantal) AS N FROM Afvoer GROUP BY Reden")->fetchAll() as $r) {
    $afvoer[$r['Reden']] = (int) $r['N'];
}

$verspild = $afvoer['Verspild'] ?? 0;
$retour   = ($afvoer['Retour leverancier'] ?? 0) + ($afvoer['Veeteelt'] ?? 0);
$somAlles = $inPakket + $verspild + $retour;
$pct      = static fn(int $n): float => $somAlles > 0 ? round($n / $somAlles * 100, 1) : 0.0;

/* ---------------- 6. gezinsgrootte-verdeling (US-RAP-05) ---------------- */
$verdeling = db()->query("
    SELECT CASE
             WHEN Grootte <= 1 THEN '1 persoon (alleenstaand)'
             WHEN Grootte <= 3 THEN '2-3 personen (klein huishouden)'
             ELSE '4+ personen (groot gezin)'
           END AS Groep,
           COUNT(*) AS Aantal
      FROM (
        SELECT k.idKlant, (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = k.idKlant) AS Grootte
          FROM Klant k WHERE k.Geaccepteerd = 1
      ) t
     GROUP BY Groep
     ORDER BY FIELD(Groep,'1 persoon (alleenstaand)','2-3 personen (klein huishouden)','4+ personen (groot gezin)')
")->fetchAll();

$totKlant = array_sum(array_map(static fn(array $r): int => (int) $r['Aantal'], $verdeling));

/* ---------------- 7. meest uitgegeven producten ---------------- */
$topProducten = db()->query("
    SELECT pr.Productnaam, c.Naam AS Categorie, SUM(pp.Aantal) AS Aantal
      FROM Pakket p
      JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
      JOIN Product pr ON pr.idProduct = pp.Product_idProduct
      JOIN Categorie c ON c.idCategorie = pr.Categorie_idCategorie
     WHERE p.Status = 'Uitgegeven'
     GROUP BY pr.idProduct
     ORDER BY Aantal DESC
     LIMIT 10
")->fetchAll();

$rapport = [
    'pakketten' => [
        'ytd'       => $ytd,
        'vorigJaar' => $ytdVorig,
        'groei'     => $groei,
    ],
    'waarde' => [
        'gemiddeld' => $gemiddelde,
        'totaal'    => round((float) $w['Waarde'], 2),
    ],
    'weken' => array_map(static fn(array $r): array => [
        'label'  => 'W' . (int) $r['Weeknr'],
        'aantal' => (int) $r['Aantal'],
        'van'    => $r['Van'],
        'tot'    => $r['Tot'],
    ], $weken),
    'verloop' => $verloop,
    'bestemming' => [
        ['label' => 'Uitgegeven in pakket',        'aantal' => $inPakket, 'percentage' => $pct($inPakket)],
        ['label' => 'Retour leverancier / veeteelt','aantal' => $retour,   'percentage' => $pct($retour)],
        ['label' => 'Verspild / afgevoerd',         'aantal' => $verspild, 'percentage' => $pct($verspild)],
    ],
    'verspilling' => ['percentage' => $pct($verspild), 'doel' => 2.0, 'nuttig' => round(100 - $pct($verspild), 1)],
    'verdeling' => array_map(static fn(array $r): array => [
        'groep'      => $r['Groep'],
        'aantal'     => (int) $r['Aantal'],
        'percentage' => $totKlant > 0 ? (int) round((int) $r['Aantal'] / $totKlant * 100) : 0,
    ], $verdeling),
    'topProducten' => array_map(static fn(array $r): array => [
        'naam'      => $r['Productnaam'],
        'categorie' => $r['Categorie'],
        'aantal'    => (int) $r['Aantal'],
    ], $topProducten),
];

/* ---------------- CSV-export (US-RAP-06) ---------------- */
if ($actie === 'export') {
    $welke = (string) ($_GET['rapport'] ?? 'weken');

    $tabellen = [
        'weken'        => [['Week', 'Uitgegeven pakketten'],
                           array_map(static fn($r) => [$r['label'], $r['aantal']], $rapport['weken'])],
        'verloop'      => [['Datum', 'Voorraad (stuks)'],
                           array_map(static fn($r) => [$r['datum'], $r['stand']], $rapport['verloop'])],
        'bestemming'   => [['Bestemming', 'Aantal', 'Percentage'],
                           array_map(static fn($r) => [$r['label'], $r['aantal'], $r['percentage']], $rapport['bestemming'])],
        'verdeling'    => [['Gezinsgrootte', 'Aantal gezinnen', 'Percentage'],
                           array_map(static fn($r) => [$r['groep'], $r['aantal'], $r['percentage']], $rapport['verdeling'])],
        'topproducten' => [['Product', 'Categorie', 'Aantal uitgegeven'],
                           array_map(static fn($r) => [$r['naam'], $r['categorie'], $r['aantal']], $rapport['topProducten'])],
    ];

    if (!isset($tabellen[$welke])) {
        json_out(['fout' => 'Onbekend rapport.'], 404);
    }

    [$kop, $rijen] = $tabellen[$welke];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="voedselbank-' . $welke . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                 // BOM, zodat Excel de accenten goed leest
    fputcsv($out, $kop, ';');
    foreach ($rijen as $r) { fputcsv($out, $r, ';'); }
    fclose($out);
    exit;
}

json_out($rapport);
