<?php
declare(strict_types=1);
/**
 * Producten, voorraad en categorieën.
 *   GET  ?actie=lijst          US-VRD-06/07/08/12
 *   GET  ?actie=categorieen    US-VRD-11
 *   POST ?actie=opslaan        US-VRD-01/02/09/10
 *   POST ?actie=voorraad       US-VRD-03  {id, delta} of {id, aantal}
 *   POST ?actie=afvoeren       US-VRD-04 / US-RAP-04
 *   POST ?actie=verwijderen    US-VRD-04/05
 *   POST ?actie=categorie      US-VRD-11
 */

$actie = $_GET['actie'] ?? 'lijst';
$pdo   = db();

// ---------------------------------------------------------------
if ($actie === 'lijst') {
    vereis_login();   // ook de vrijwilliger moet de voorraad kunnen zien

    $rijen = $pdo->query("
        SELECT p.idProduct, p.Productnaam, p.EAN, p.Aantal_voorraad, p.Min_voorraad,
               p.Waarde, p.Houdbaar_tot, p.Locatie, p.Allergenen,
               c.idCategorie, c.Naam AS CategorieNaam, c.Kort AS CategorieKort,
               (SELECT COUNT(*) FROM Product_has_Pakket pp WHERE pp.Product_idProduct = p.idProduct) AS InPakketten
          FROM Product p
          JOIN Categorie c ON c.idCategorie = p.Categorie_idCategorie
      ORDER BY p.Productnaam
    ")->fetchAll();

    $totaal       = (int) $pdo->query("SELECT COALESCE(SUM(Aantal_voorraad),0) FROM Product")->fetchColumn();
    $waarschuwing = (int) instelling('tht_waarschuwing_dagen', '3');

    $vandaag = new DateTimeImmutable('today');
    $uit = [];
    foreach ($rijen as $r) {
        $tht = $r['Houdbaar_tot'] ? new DateTimeImmutable($r['Houdbaar_tot']) : null;
        $dagen = $tht ? (int) $vandaag->diff($tht)->format('%r%a') : null;

        $uit[] = [
            'id'          => (int) $r['idProduct'],
            'naam'        => $r['Productnaam'],
            'ean'         => $r['EAN'],
            'voorraad'    => (int) $r['Aantal_voorraad'],
            'min'         => (int) $r['Min_voorraad'],
            'waarde'      => (float) $r['Waarde'],
            'tht'         => $r['Houdbaar_tot'],
            'thtDagen'    => $dagen,
            'verlopen'    => $dagen !== null && $dagen < 0,
            'bijnaVerlopen' => $dagen !== null && $dagen >= 0 && $dagen <= $waarschuwing,
            'onderMin'    => (int) $r['Aantal_voorraad'] < (int) $r['Min_voorraad'],
            'locatie'     => $r['Locatie'],
            'allergenen'  => $r['Allergenen'] ? explode(',', $r['Allergenen']) : [],
            'categorie'   => ['id' => (int) $r['idCategorie'], 'naam' => $r['CategorieNaam'], 'kort' => $r['CategorieKort']],
            'inPakketten' => (int) $r['InPakketten'],
        ];
    }

    json_out([
        'producten' => $uit,
        'magazijn'  => [
            'totaal'   => $totaal,
            'soorten'  => count($uit),
            'verlopen' => count(array_filter($uit, static fn($r) => $r['verlopen'])),
            'onderMin' => count(array_filter($uit, static fn($r) => $r['onderMin'])),
        ],
        'rechten' => [
            'beheren'    => mag('product_beheren'),
            'corrigeren' => mag('voorraad_corrigeren'),
        ],
    ]);
}

// ---------------------------------------------------------------
if ($actie === 'categorieen') {
    vereis_login();
    $rijen = $pdo->query("
        SELECT c.idCategorie, c.Naam, c.Kort, c.Omschrijving,
               (SELECT COUNT(*) FROM Product p WHERE p.Categorie_idCategorie = c.idCategorie) AS Aantal
          FROM Categorie c ORDER BY c.idCategorie
    ")->fetchAll();

    json_out(array_map(fn($r) => [
        'id'      => (int) $r['idCategorie'],
        'naam'    => $r['Naam'],
        'kort'    => $r['Kort'],
        'oms'     => $r['Omschrijving'],
        'aantal'  => (int) $r['Aantal'],
    ], $rijen));
}

// ---------------------------------------------------------------
if ($actie === 'categorie') {              // US-VRD-11
    alleen_post();
    vereis_permissie('product_beheren');   // directie en magazijnmedewerker (PRD-5)

    $in   = json_in();
    $id   = (int) ($in['id'] ?? 0);
    $naam = trim((string) ($in['naam'] ?? ''));
    $kort = trim((string) ($in['kort'] ?? ''));
    $oms  = trim((string) ($in['oms'] ?? ''));

    if (mb_strlen($naam) < 2) {
        json_out(['fouten' => ['naam' => 'Naam is verplicht.']], 422);
    }

    $check = $pdo->prepare("SELECT idCategorie FROM Categorie WHERE Naam = ? AND idCategorie <> ?");
    $check->execute([$naam, $id]);
    if ($check->fetchColumn()) {
        json_out(['fouten' => ['naam' => 'Deze categorie bestaat al.']], 422);
    }

    if ($id > 0) {
        $pdo->prepare("UPDATE Categorie SET Naam=?, Kort=?, Omschrijving=? WHERE idCategorie=?")
            ->execute([$naam, $kort ?: mb_substr($naam, 0, 20), $oms, $id]);
    } else {
        $pdo->prepare("INSERT INTO Categorie (Naam, Kort, Omschrijving) VALUES (?,?,?)")
            ->execute([$naam, $kort ?: mb_substr($naam, 0, 20), $oms]);
        $id = (int) $pdo->lastInsertId();
    }
    json_out(['ok' => true, 'id' => $id]);
}

// ---------------------------------------------------------------
if ($actie === 'categorie_verwijderen') {  // US-VRD-11
    alleen_post();
    vereis_permissie('product_beheren');

    $id = (int) (json_in()['id'] ?? 0);
    if ($id <= 0) { json_out(['fout' => 'Onbekende categorie.'], 422); }

    $q = $pdo->prepare("
        SELECT c.Naam,
               (SELECT COUNT(*) FROM Product p WHERE p.Categorie_idCategorie = c.idCategorie) AS Aantal
          FROM Categorie c WHERE c.idCategorie = ?
    ");
    $q->execute([$id]);
    $c = $q->fetch();
    if (!$c) { json_out(['fout' => 'Categorie niet gevonden.'], 404); }

    // Een categorie met producten laten verdwijnen zou de producten meenemen
    // (en daarmee de pakkethistorie). De database houdt dat met RESTRICT tegen;
    // hier geven we er een begrijpelijke melding bij.
    $aantal = (int) $c['Aantal'];
    if ($aantal > 0) {
        json_out([
            'fout'      => 'In "' . $c['Naam'] . '" ' . ($aantal === 1 ? 'staat nog 1 product' : 'staan nog ' . $aantal . ' producten') . '.',
            'reden'     => 'heeft_producten',
            'aantal'    => $aantal,
            'categorie' => $c['Naam'],
            'suggestie' => 'Zet die producten eerst in een andere categorie. Daarna kan deze categorie weg.',
        ], 409);
    }

    $pdo->prepare("DELETE FROM Categorie WHERE idCategorie = ?")->execute([$id]);
    json_out(['ok' => true, 'melding' => 'Categorie "' . $c['Naam'] . '" is verwijderd.']);
}

// ---------------------------------------------------------------
if ($actie === 'categorie_verplaatsen') {  // producten overzetten voor het verwijderen
    alleen_post();
    vereis_permissie('product_beheren');

    $in   = json_in();
    $van  = (int) ($in['van']  ?? 0);
    $naar = (int) ($in['naar'] ?? 0);
    if ($van <= 0 || $naar <= 0 || $van === $naar) {
        json_out(['fout' => 'Kies een andere categorie om de producten in te zetten.'], 422);
    }

    $c = $pdo->prepare("SELECT idCategorie, Naam FROM Categorie WHERE idCategorie IN (?,?)");
    $c->execute([$van, $naar]);
    if (count($c->fetchAll()) !== 2) { json_out(['fout' => 'Categorie niet gevonden.'], 404); }

    $u = $pdo->prepare("UPDATE Product SET Categorie_idCategorie = ? WHERE Categorie_idCategorie = ?");
    $u->execute([$naar, $van]);

    json_out(['ok' => true, 'verplaatst' => $u->rowCount()]);
}

// ---------------------------------------------------------------
if ($actie === 'opslaan') {                // US-VRD-01/02/09/10
    alleen_post();
    vereis_permissie('product_beheren');

    $in  = json_in();
    $id  = (int) ($in['id'] ?? 0);
    $naam = trim((string) ($in['naam'] ?? ''));
    $ean  = preg_replace('/\D/', '', (string) ($in['ean'] ?? ''));
    $cat  = (int) ($in['categorie'] ?? 0);
    $vrd  = (int) ($in['voorraad'] ?? 0);
    $min  = (int) ($in['min'] ?? 0);
    $tht  = trim((string) ($in['tht'] ?? ''));
    $loc  = trim((string) ($in['locatie'] ?? ''));
    $alg  = trim((string) ($in['allergenen'] ?? ''));
    $wrd  = round((float) ($in['waarde'] ?? 0), 2);

    $fouten = [];
    if (mb_strlen($naam) < 2)                        { $fouten['naam']     = 'Productnaam is verplicht.'; }
    if (!preg_match('/^\d{13}$/', $ean))             { $fouten['ean']      = 'Een EAN bestaat uit 13 cijfers.'; }
    if ($cat <= 0)                                   { $fouten['categorie']= 'Kies een categorie.'; }
    if ($vrd < 0)                                    { $fouten['voorraad'] = 'Voorraad kan niet negatief zijn.'; }
    if ($wrd < 0 || $wrd > 9999)                     { $fouten['waarde']   = 'Vul een bedrag tussen 0 en 9999 in.'; }
    if ($tht !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tht)) { $fouten['tht'] = 'Ongeldige datum.'; }

    // uniciteit: US-VRD-09 (EAN) en US-VRD-10 (productnaam)
    $q = $pdo->prepare("SELECT Productnaam, EAN FROM Product WHERE (Productnaam = ? OR EAN = ?) AND idProduct <> ?");
    $q->execute([$naam, $ean, $id]);
    foreach ($q->fetchAll() as $r) {
        if (strcasecmp($r['Productnaam'], $naam) === 0) { $fouten['naam'] = 'Dit product bestaat al in het magazijn.'; }
        if ($r['EAN'] === $ean)                          { $fouten['ean']  = 'Deze streepjescode is al in gebruik.'; }
    }

    if ($fouten) { json_out(['fouten' => $fouten], 422); }

    if ($id > 0) {
        $pdo->prepare("
            UPDATE Product SET Productnaam=?, EAN=?, Aantal_voorraad=?, Min_voorraad=?, Waarde=?,
                   Houdbaar_tot=?, Locatie=?, Allergenen=?, Categorie_idCategorie=?
             WHERE idProduct=?
        ")->execute([$naam, $ean, $vrd, $min, $wrd, $tht ?: null, $loc ?: null, $alg ?: null, $cat, $id]);
    } else {
        $pdo->prepare("
            INSERT INTO Product (Productnaam, EAN, Aantal_voorraad, Min_voorraad, Waarde, Houdbaar_tot, Locatie, Allergenen, Categorie_idCategorie)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([$naam, $ean, $vrd, $min, $wrd, $tht ?: null, $loc ?: null, $alg ?: null, $cat]);
        $id = (int) $pdo->lastInsertId();
    }

    json_out(['ok' => true, 'id' => $id, 'melding' => 'Product opgeslagen.']);
}

// ---------------------------------------------------------------
if ($actie === 'afvoeren') {               // US-VRD-04 / US-RAP-04
    alleen_post();
    vereis_permissie('voorraad_corrigeren');
    $gebruiker = huidige_gebruiker();

    $in     = json_in();
    $id     = (int) ($in['id'] ?? 0);
    $aantal = (int) ($in['aantal'] ?? 0);
    $reden  = (string) ($in['reden'] ?? '');
    $toel   = trim((string) ($in['toelichting'] ?? ''));

    $fouten = [];
    if ($aantal <= 0) { $fouten['aantal'] = 'Vul een aantal groter dan 0 in.'; }
    if (!in_array($reden, ['Verspild', 'Retour leverancier', 'Veeteelt'], true)) {
        $fouten['reden'] = 'Kies een reden.';
    }
    if ($fouten) { json_out(['fouten' => $fouten], 422); }

    $q = $pdo->prepare("SELECT Productnaam, Aantal_voorraad FROM Product WHERE idProduct = ?");
    $q->execute([$id]);
    $p = $q->fetch();
    if (!$p) { json_out(['fout' => 'Product niet gevonden.'], 404); }

    if ((int) $p['Aantal_voorraad'] < $aantal) {
        json_out([
            'fouten' => ['aantal' => 'Er liggen maar ' . (int) $p['Aantal_voorraad'] . ' stuks in het magazijn.'],
        ], 422);
    }

    // Afvoeren is geen verwijdering: het blijft als regel in de tabel Afvoer
    // staan, zodat het verspillingsrapport klopt (US-RAP-04).
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO Afvoer (Product_idProduct, Aantal, Datum, Reden, Toelichting, Gebruiker_idGebruiker)
            VALUES (?,?,CURDATE(),?,?,?)
        ")->execute([$id, $aantal, $reden, $toel ?: null, $gebruiker['idGebruiker']]);

        $pdo->prepare("UPDATE Product SET Aantal_voorraad = Aantal_voorraad - ? WHERE idProduct = ?")
            ->execute([$aantal, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_out([
        'ok'      => true,
        'melding' => $aantal . ' x ' . $p['Productnaam'] . ' afgevoerd (' . $reden . ').',
    ]);
}

// ---------------------------------------------------------------
if ($actie === 'voorraad') {               // US-VRD-03 / US-GBR-08
    alleen_post();
    vereis_permissie('voorraad_corrigeren');

    $in = json_in();
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) { json_out(['fout' => 'Onbekend product.'], 422); }

    $q = $pdo->prepare("SELECT Aantal_voorraad FROM Product WHERE idProduct = ?");
    $q->execute([$id]);
    $huidig = $q->fetchColumn();
    if ($huidig === false) { json_out(['fout' => 'Product niet gevonden.'], 404); }

    $nieuw = array_key_exists('aantal', $in)
        ? (int) $in['aantal']
        : (int) $huidig + (int) ($in['delta'] ?? 0);

    if ($nieuw < 0) {
        json_out(['fout' => 'De voorraad kan niet onder 0 komen.'], 422);
    }

    $pdo->prepare("UPDATE Product SET Aantal_voorraad = ? WHERE idProduct = ?")->execute([$nieuw, $id]);
    json_out(['ok' => true, 'voorraad' => $nieuw, 'melding' => 'Voorraad bijgewerkt naar ' . $nieuw . ' stuks.']);
}

// ---------------------------------------------------------------
if ($actie === 'verwijderen') {            // US-VRD-04 / US-VRD-05
    alleen_post();
    vereis_permissie('product_beheren');

    $id = (int) (json_in()['id'] ?? 0);
    if ($id <= 0) { json_out(['fout' => 'Onbekend product.'], 422); }

    $q = $pdo->prepare("
        SELECT p.Productnaam,
               (SELECT COUNT(*) FROM Product_has_Pakket pp WHERE pp.Product_idProduct = p.idProduct) AS inPakket,
               (SELECT COUNT(*) FROM Levering_has_Product lp WHERE lp.Product_idProduct = p.idProduct) AS inLevering
          FROM Product p WHERE p.idProduct = ?
    ");
    $q->execute([$id]);
    $p = $q->fetch();
    if (!$p) { json_out(['fout' => 'Product niet gevonden.'], 404); }

    // US-VRD-05: historie mag niet verloren gaan
    if ((int) $p['inPakket'] > 0) {
        json_out([
            'fout'       => 'Dit product kan niet worden verwijderd.',
            'reden'      => 'in_pakket',
            'aantal'     => (int) $p['inPakket'],
            'product'    => $p['Productnaam'],
            'suggestie'  => 'Zet de voorraad op 0 zodat het product niet meer wordt gekozen.',
        ], 409);
    }
    if ((int) $p['inLevering'] > 0) {
        json_out([
            'fout'      => 'Dit product kan niet worden verwijderd.',
            'reden'     => 'in_levering',
            'aantal'    => (int) $p['inLevering'],
            'product'   => $p['Productnaam'],
            'suggestie' => 'Het product staat in de leveringshistorie. Zet de voorraad op 0.',
        ], 409);
    }

    $q = $pdo->prepare("SELECT COUNT(*) FROM Afvoer WHERE Product_idProduct = ?");
    $q->execute([$id]);
    if ((int) $q->fetchColumn() > 0) {
        json_out([
            'fout'      => 'Dit product staat in de afvoerhistorie en kan niet worden verwijderd.',
            'reden'     => 'in_afvoer',
            'product'   => $p['Productnaam'],
            'suggestie' => 'Zet de voorraad op 0 zodat het product niet meer wordt gekozen.',
        ], 409);
    }

    $pdo->prepare("DELETE FROM Product WHERE idProduct = ?")->execute([$id]);
    json_out(['ok' => true, 'melding' => $p['Productnaam'] . ' is verwijderd.']);
}

json_out(['fout' => 'Onbekende actie.'], 400);
