<?php
declare(strict_types=1);
/**
 * Vrijdag Uitgiftepunt — US-PAK-09, US-PAK-12, US-WRK-01 t/m US-WRK-06.
 *
 *   GET  ?actie=wachtrij       alle klaarstaande pakketten van deze week
 *   GET  ?actie=zoek&q=        klant zoeken op klantnummer of naam
 *   GET  ?actie=kaart&id=      één pakket opnieuw ophalen
 *   POST ?actie=identiteit     legitimatie vastleggen (US-WRK-01)
 *   POST ?actie=uitgeven       pakket definitief uitgeven
 *   POST ?actie=nietopgehaald  pakket terugboeken naar de voorraad (US-WRK-04)
 */

$actie = $_GET['actie'] ?? 'wachtrij';

function week_start(): string
{
    return (new DateTimeImmutable('monday this week'))->format('Y-m-d');
}

/** Eén pakket met alles wat het uitgiftescherm toont. */
function uitgifte_kaart(int $pakketId): ?array
{
    $q = db()->prepare("
        SELECT p.idPakket, p.Status, p.Samenstellingsdatum, p.Uitgiftedatum, p.Opgehaald,
               p.Identiteit_gecontroleerd, p.Legitimatie,
               k.idKlant, k.Gezinsnaam, k.Adres, k.Plaats,
               CONCAT('K-', LPAD(k.idKlant, 3, '0')) AS Klantnummer,
               (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = k.idKlant) AS Gezinsgrootte
          FROM Pakket p
          JOIN Klant k ON k.idKlant = p.Klant_idKlant
         WHERE p.idPakket = ?
    ");
    $q->execute([$pakketId]);
    $p = $q->fetch();
    if (!$p) { return null; }

    $i = db()->prepare("
        SELECT pp.Aantal, pr.Productnaam, c.Naam AS Categorie
          FROM Product_has_Pakket pp
          JOIN Product pr ON pr.idProduct = pp.Product_idProduct
          JOIN Categorie c ON c.idCategorie = pr.Categorie_idCategorie
         WHERE pp.Pakket_idPakket = ?
         ORDER BY c.Naam, pr.Productnaam
    ");
    $i->execute([$pakketId]);

    $w = db()->prepare("
        SELECT DISTINCT w.Naam FROM Gezinslid_has_Wens gw
          JOIN Wens w ON w.idWens = gw.Wens_idWens
          JOIN Gezinslid g ON g.idGezinslid = gw.Gezinslid_idGezinslid
         WHERE g.Klant_idKlant = ?
    ");
    $w->execute([(int) $p['idKlant']]);

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
        'inhoud'        => array_map(static fn(array $r): array => [
            'naam'      => $r['Productnaam'],
            'aantal'    => (int) $r['Aantal'],
            'categorie' => $r['Categorie'],
        ], $i->fetchAll()),
        'wensen'        => $w->fetchAll(PDO::FETCH_COLUMN),
    ];
}

/* ------------------------------------------------------------------ */
if ($actie === 'wachtrij') {                 // US-WRK-02: overzicht van de dag
    vereis_permissie('pakket_uitgeven');
    $maandag = week_start();

    $q = db()->prepare("
        SELECT p.idPakket, p.Status, p.Uitgiftedatum, p.Identiteit_gecontroleerd,
               k.Gezinsnaam, CONCAT('K-', LPAD(k.idKlant, 3, '0')) AS Klantnummer,
               (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = k.idKlant) AS Gezinsgrootte,
               COALESCE(SUM(pp.Aantal), 0) AS Aantal
          FROM Pakket p
          JOIN Klant k ON k.idKlant = p.Klant_idKlant
          LEFT JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
         WHERE p.Samenstellingsdatum >= ?
         GROUP BY p.idPakket
         ORDER BY FIELD(p.Status,'Klaar','Concept','Uitgegeven'), k.Gezinsnaam
    ");
    $q->execute([$maandag]);
    $rijen = $q->fetchAll();

    $klaar = $uitgegeven = $concept = [];
    foreach ($rijen as $r) {
        $item = [
            'pakketId'      => (int) $r['idPakket'],
            'nummer'        => $r['Klantnummer'],
            'klant'         => $r['Gezinsnaam'],
            'gezinsgrootte' => (int) $r['Gezinsgrootte'],
            'aantal'        => (int) $r['Aantal'],
            'status'        => $r['Status'],
            'uitgifte'      => $r['Uitgiftedatum'],
            'gecontroleerd' => (bool) $r['Identiteit_gecontroleerd'],
        ];
        if ($r['Status'] === 'Klaar')            { $klaar[] = $item; }
        elseif ($r['Status'] === 'Uitgegeven')   { $uitgegeven[] = $item; }
        else                                     { $concept[] = $item; }
    }

    json_out([
        'weekstart'  => $maandag,
        'klaar'      => $klaar,
        'concept'    => $concept,
        'uitgegeven' => $uitgegeven,
    ]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'kaart') {                    // één pakket opnieuw ophalen
    vereis_permissie('pakket_uitgeven');
    $kaart = uitgifte_kaart((int) ($_GET['id'] ?? 0));
    if ($kaart === null) {
        json_out(['fout' => 'Pakket niet gevonden.'], 404);
    }
    json_out($kaart);
}

/* ------------------------------------------------------------------ */
if ($actie === 'zoek') {                     // "Scan pasje of typ het cliënt-ID"
    vereis_permissie('pakket_uitgeven');

    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q === '') {
        json_out(['fout' => 'Vul een cliëntnummer of naam in.'], 422);
    }

    // 8821 / K-008 / k008 / "El Hassani" mogen allemaal
    $cijfers = preg_replace('/\D/', '', $q);
    $zoek    = '%' . $q . '%';

    $s = db()->prepare("
        SELECT k.idKlant
          FROM Klant k
         WHERE k.Gezinsnaam LIKE ?
            OR CONCAT('K-', LPAD(k.idKlant, 3, '0')) = UPPER(?)
            OR (? <> '' AND k.idKlant = ?)
         ORDER BY k.Gezinsnaam
         LIMIT 10
    ");
    $s->execute([$zoek, $q, $cijfers, $cijfers === '' ? 0 : (int) $cijfers]);
    $ids = $s->fetchAll(PDO::FETCH_COLUMN);

    if (!$ids) {
        json_out(['fout' => 'Geen cliënt gevonden voor "' . $q . '".'], 404);
    }

    $maandag = week_start();
    $uit = [];
    foreach ($ids as $klantId) {
        $p = db()->prepare("
            SELECT idPakket FROM Pakket
             WHERE Klant_idKlant = ? AND Samenstellingsdatum >= ?
             ORDER BY FIELD(Status,'Klaar','Uitgegeven','Concept'), idPakket DESC LIMIT 1
        ");
        $p->execute([(int) $klantId, $maandag]);
        $pakketId = $p->fetchColumn();

        if ($pakketId) {
            $uit[] = uitgifte_kaart((int) $pakketId);
            continue;
        }

        $k = db()->prepare("
            SELECT idKlant, Gezinsnaam, Adres, Plaats,
                   CONCAT('K-', LPAD(idKlant, 3, '0')) AS Klantnummer,
                   (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = Klant.idKlant) AS Gezinsgrootte
              FROM Klant WHERE idKlant = ?
        ");
        $k->execute([(int) $klantId]);
        $r = $k->fetch();

        $uit[] = [
            'pakketId' => null,
            'geenPakket' => true,
            'klant'    => [
                'id'            => (int) $r['idKlant'],
                'nummer'        => $r['Klantnummer'],
                'naam'          => $r['Gezinsnaam'],
                'adres'         => $r['Adres'] . ', ' . $r['Plaats'],
                'gezinsgrootte' => (int) $r['Gezinsgrootte'],
            ],
            'inhoud'   => [],
            'wensen'   => [],
        ];
    }

    json_out(['resultaten' => $uit]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'identiteit') {               // US-WRK-01
    alleen_post();
    vereis_permissie('pakket_uitgeven');

    $in   = json_in();
    $id   = (int) ($in['pakketId'] ?? 0);
    $soort= (string) ($in['legitimatie'] ?? '');

    if (!in_array($soort, ['Paspoort', 'Rijbewijs', 'ID-kaart'], true)) {
        json_out(['fouten' => ['legitimatie' => 'Kies paspoort, rijbewijs of ID-kaart.']], 422);
    }

    $n = db()->prepare("
        UPDATE Pakket SET Identiteit_gecontroleerd = 1, Legitimatie = ?
         WHERE idPakket = ? AND Status <> 'Uitgegeven'
    ");
    $n->execute([$soort, $id]);

    if ($n->rowCount() === 0) {
        json_out(['fout' => 'Dit pakket bestaat niet of is al uitgegeven.'], 409);
    }

    json_out(['ok' => true, 'melding' => 'Geïdentificeerd met: ' . $soort]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'uitgeven') {                 // US-PAK-09 / US-PAK-12
    alleen_post();
    $gebruiker = vereis_permissie('pakket_uitgeven');

    $id = (int) (json_in()['pakketId'] ?? 0);

    $q = db()->prepare("
        SELECT p.Status, p.Identiteit_gecontroleerd, k.Gezinsnaam,
               (SELECT COUNT(*) FROM Product_has_Pakket pp WHERE pp.Pakket_idPakket = p.idPakket) AS Regels
          FROM Pakket p JOIN Klant k ON k.idKlant = p.Klant_idKlant
         WHERE p.idPakket = ?
    ");
    $q->execute([$id]);
    $p = $q->fetch();

    if (!$p) {
        json_out(['fout' => 'Pakket niet gevonden.'], 404);
    }
    if ($p['Status'] === 'Uitgegeven') {
        json_out(['fout' => 'Dit pakket is al uitgegeven.', 'reden' => 'al_uitgegeven'], 409);
    }
    if ((int) $p['Regels'] === 0) {
        json_out(['fout' => 'Dit pakket is nog leeg.'], 409);
    }
    // De identiteitscontrole is een voorwaarde, geen vinkje achteraf.
    if (!(int) $p['Identiteit_gecontroleerd']) {
        json_out([
            'fout'  => 'Leg eerst vast met welk legitimatiebewijs de cliënt zich heeft geïdentificeerd.',
            'reden' => 'geen_identiteit',
        ], 409);
    }

    db()->prepare("
        UPDATE Pakket
           SET Status = 'Uitgegeven', Opgehaald = 1, Uitgiftedatum = CURDATE(), Uitgegeven_door = ?
         WHERE idPakket = ?
    ")->execute([$gebruiker['idGebruiker'], $id]);

    json_out(['ok' => true, 'melding' => 'Pakket uitgegeven aan ' . $p['Gezinsnaam'] . '.']);
}

/* ------------------------------------------------------------------ */
if ($actie === 'nietopgehaald') {            // US-WRK-04
    alleen_post();
    vereis_permissie('pakket_uitgeven');

    $id = (int) (json_in()['pakketId'] ?? 0);

    $q = db()->prepare("SELECT Status FROM Pakket WHERE idPakket = ?");
    $q->execute([$id]);
    $status = $q->fetchColumn();

    if ($status === false) {
        json_out(['fout' => 'Pakket niet gevonden.'], 404);
    }
    if ($status === 'Uitgegeven') {
        json_out(['fout' => 'Dit pakket is al uitgegeven en blijft in de historie staan.'], 409);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = $pdo->prepare("SELECT Product_idProduct, Aantal FROM Product_has_Pakket WHERE Pakket_idPakket = ?");
        $o->execute([$id]);
        $bij = $pdo->prepare("UPDATE Product SET Aantal_voorraad = Aantal_voorraad + ? WHERE idProduct = ?");
        foreach ($o->fetchAll() as $r) {
            $bij->execute([(int) $r['Aantal'], (int) $r['Product_idProduct']]);
        }
        $pdo->prepare("DELETE FROM Pakket WHERE idPakket = ?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_out(['ok' => true, 'melding' => 'Pakket teruggeboekt, de producten staan weer in de voorraad.']);
}

json_out(['fout' => 'Onbekende actie.'], 400);
