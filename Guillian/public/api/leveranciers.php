<?php
declare(strict_types=1);
require __DIR__ . '/../../src/auth.php';
api_boot();

/**
 * Leveranciers en leveringen — US-LEV-01 t/m US-LEV-06.
 *
 *   GET  ?actie=lijst           leveranciers + leveringsgeschiedenis
 *   GET  ?actie=leverancier&id= één leverancier
 *   POST ?actie=opslaan         toevoegen / wijzigen      (US-LEV-01/02)
 *   POST ?actie=actief          activeren / deactiveren   (US-LEV-03)
 *   POST ?actie=verwijderen     verwijderen mits geen historie (US-LEV-03)
 *   POST ?actie=levering        levering boeken -> voorraad bij (US-LEV-04/05)
 */

$actie = $_GET['actie'] ?? 'lijst';

/* ------------------------------------------------------------------ */
if ($actie === 'lijst') {
    vereis_login();

    $lev = db()->query("
        SELECT l.*,
               (SELECT COUNT(*) FROM Levering v WHERE v.Leverancier_idLeverancier = l.idLeverancier) AS Aantal_leveringen,
               (SELECT MAX(v.Leverdatum) FROM Levering v WHERE v.Leverancier_idLeverancier = l.idLeverancier) AS Laatste_levering
          FROM Leverancier l
         ORDER BY l.Actief DESC, l.Bedrijfsnaam
    ")->fetchAll();

    // leveringsgeschiedenis in één keer ophalen (geen query per rij)
    $reg = db()->query("
        SELECT v.idLevering, v.Leverancier_idLeverancier, v.Leverdatum, v.Levertijd,
               g.Naam AS Verwerkt_door,
               GROUP_CONCAT(CONCAT(p.Productnaam, ' \u{00d7} ', lp.Aantal) ORDER BY p.Productnaam SEPARATOR ', ') AS Inhoud,
               COALESCE(SUM(lp.Aantal), 0) AS Totaal
          FROM Levering v
          LEFT JOIN Levering_has_Product lp ON lp.Levering_idLevering = v.idLevering
          LEFT JOIN Product p ON p.idProduct = lp.Product_idProduct
          LEFT JOIN Gebruiker g ON g.idGebruiker = v.Verwerkt_door
         GROUP BY v.idLevering
         ORDER BY v.Leverdatum DESC
    ")->fetchAll();

    $perLev = [];
    foreach ($reg as $r) {
        $perLev[(int) $r['Leverancier_idLeverancier']][] = [
            'id'       => (int) $r['idLevering'],
            'datum'    => $r['Leverdatum'],
            'tijd'     => $r['Levertijd'] ? substr((string) $r['Levertijd'], 0, 5) : null,
            'inhoud'   => $r['Inhoud'] ?? '',
            'totaal'   => (int) $r['Totaal'],
            'door'     => $r['Verwerkt_door'],
        ];
    }

    $uit = [];
    foreach ($lev as $l) {
        $id    = (int) $l['idLeverancier'];
        $uit[] = [
            'id'             => $id,
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
            'leveringen'     => $perLev[$id] ?? [],
        ];
    }

    json_out([
        'leveranciers' => $uit,
        'rechten'      => ['beheren' => mag('leverancier_beheren'), 'boeken' => mag('levering_boeken')],
    ]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'opslaan') {                       // US-LEV-01 / US-LEV-02
    alleen_post();
    vereis_permissie('leverancier_beheren');

    $in = json_in();
    $id = (int) ($in['id'] ?? 0);

    $v = [
        'Bedrijfsnaam'   => trim((string) ($in['bedrijfsnaam']   ?? '')),
        'Adres'          => trim((string) ($in['adres']          ?? '')),
        'Postcode'       => strtoupper(trim((string) ($in['postcode'] ?? ''))),
        'Plaats'         => trim((string) ($in['plaats']         ?? '')),
        'Contactpersoon' => trim((string) ($in['contactpersoon'] ?? '')),
        'Email'          => trim((string) ($in['email']          ?? '')),
        'Telefoon'       => trim((string) ($in['telefoon']       ?? '')),
        'Categorie'      => trim((string) ($in['categorie']      ?? 'Overig')),
    ];

    $fouten = [];
    if ($v['Bedrijfsnaam'] === '')   { $fouten['bedrijfsnaam']   = 'Vul de bedrijfsnaam in.'; }
    if ($v['Adres'] === '')          { $fouten['adres']          = 'Vul het adres in.'; }
    if ($v['Contactpersoon'] === '') { $fouten['contactpersoon'] = 'Vul de contactpersoon in.'; }
    if ($v['Email'] === '' || !filter_var($v['Email'], FILTER_VALIDATE_EMAIL)) {
        $fouten['email'] = 'Vul een geldig e-mailadres in.';
    }
    if (!preg_match('/^[0-9 +\-]{10,20}$/', $v['Telefoon'])) {
        $fouten['telefoon'] = 'Vul een geldig telefoonnummer in.';
    }
    if ($v['Postcode'] !== '' && !preg_match('/^[1-9][0-9]{3} ?[A-Z]{2}$/', $v['Postcode'])) {
        $fouten['postcode'] = 'Gebruik het formaat 1234 AB.';
    }
    if ($fouten) {
        json_out(['fouten' => $fouten], 422);
    }

    // dubbele bedrijfsnaam voorkomen
    $q = db()->prepare("SELECT idLeverancier FROM Leverancier WHERE Bedrijfsnaam = ? AND idLeverancier <> ?");
    $q->execute([$v['Bedrijfsnaam'], $id]);
    if ($q->fetch()) {
        json_out(['fouten' => ['bedrijfsnaam' => 'Er bestaat al een leverancier met deze naam.']], 422);
    }

    $datum = ($in['volgendeDatum'] ?? '') !== '' ? (string) $in['volgendeDatum'] : null;
    $tijd  = ($in['volgendeTijd']  ?? '') !== '' ? (string) $in['volgendeTijd']  : null;

    if ($id > 0) {
        db()->prepare("
            UPDATE Leverancier
               SET Bedrijfsnaam=?, Adres=?, Postcode=?, Plaats=?, Contactpersoon=?,
                   Email=?, Telefoon=?, Categorie=?, Volgende_leverdatum=?, Volgende_levertijd=?
             WHERE idLeverancier=?
        ")->execute([...array_values($v), $datum, $tijd, $id]);
        json_out(['ok' => true, 'id' => $id, 'melding' => 'Leverancier bijgewerkt.']);
    }

    db()->prepare("
        INSERT INTO Leverancier
            (Bedrijfsnaam, Adres, Postcode, Plaats, Contactpersoon, Email, Telefoon,
             Categorie, Volgende_leverdatum, Volgende_levertijd)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ")->execute([...array_values($v), $datum, $tijd]);

    json_out(['ok' => true, 'id' => (int) db()->lastInsertId(), 'melding' => 'Leverancier toegevoegd.']);
}

/* ------------------------------------------------------------------ */
if ($actie === 'actief') {                        // US-LEV-03 (zachte variant)
    alleen_post();
    vereis_permissie('leverancier_beheren');

    $in = json_in();
    $id = (int) ($in['id'] ?? 0);
    $aan = (bool) ($in['actief'] ?? true);

    db()->prepare("UPDATE Leverancier SET Actief = ? WHERE idLeverancier = ?")
        ->execute([$aan ? 1 : 0, $id]);

    json_out(['ok' => true, 'melding' => $aan ? 'Leverancier geactiveerd.' : 'Leverancier op non-actief gezet.']);
}

/* ------------------------------------------------------------------ */
if ($actie === 'verwijderen') {                   // US-LEV-03
    alleen_post();
    vereis_permissie('leverancier_beheren');

    $in = json_in();
    $id = (int) ($in['id'] ?? 0);

    // Historie beschermen: leveringen blijven bewaard, dus een leverancier
    // met geschiedenis wordt niet verwijderd maar op non-actief gezet.
    $q = db()->prepare("SELECT COUNT(*) FROM Levering WHERE Leverancier_idLeverancier = ?");
    $q->execute([$id]);
    $aantal = (int) $q->fetchColumn();

    if ($aantal > 0) {
        json_out([
            'fout'       => "Deze leverancier heeft {$aantal} geregistreerde levering(en). "
                          . 'Die geschiedenis mag niet verdwijnen.',
            'reden'      => 'heeft_historie',
            'suggestie'  => 'Zet de leverancier op non-actief. Hij verdwijnt dan uit de keuzelijsten, '
                          . 'maar de leveringen blijven in de rapportages staan.',
        ], 409);
    }

    db()->prepare("DELETE FROM Leverancier WHERE idLeverancier = ?")->execute([$id]);
    json_out(['ok' => true, 'melding' => 'Leverancier verwijderd.']);
}

/* ------------------------------------------------------------------ */
if ($actie === 'levering') {                      // US-LEV-04 / US-LEV-05 / US-VRD-01
    alleen_post();
    vereis_permissie('levering_boeken');
    $gebruiker = huidige_gebruiker();

    $in     = json_in();
    $levId  = (int) ($in['leverancier'] ?? 0);
    $datum  = (string) ($in['datum'] ?? date('Y-m-d'));
    $tijd   = ($in['tijd'] ?? '') !== '' ? (string) $in['tijd'] : null;
    $regels = is_array($in['regels'] ?? null) ? $in['regels'] : [];

    $fouten = [];
    if ($levId <= 0)                                   { $fouten['leverancier'] = 'Kies een leverancier.'; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum))  { $fouten['datum'] = 'Vul een geldige datum in.'; }
    if (!$regels)                                      { $fouten['regels'] = 'Voeg minimaal één product toe.'; }
    if ($fouten) {
        json_out(['fouten' => $fouten], 422);
    }

    // capaciteitscontrole vóór het boeken (US-VRD-11)
    $capaciteit = (int) instelling('magazijn_capaciteit', '6500');
    $huidig     = (int) db()->query("SELECT COALESCE(SUM(Aantal_voorraad),0) FROM Product")->fetchColumn();
    $erbij      = 0;
    foreach ($regels as $r) { $erbij += max(0, (int) ($r['aantal'] ?? 0)); }

    if (!($in['forceer'] ?? false) && $huidig + $erbij > $capaciteit) {
        json_out([
            'fout'       => 'Het magazijn is te vol voor deze levering.',
            'reden'      => 'magazijn_vol',
            'capaciteit' => [
                'capaciteit' => $capaciteit,
                'huidig'     => $huidig,
                'erbij'      => $erbij,
                'tekort'     => $huidig + $erbij - $capaciteit,
            ],
        ], 409);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO Levering (Leverancier_idLeverancier, Leverdatum, Levertijd, Verwerkt_door)
            VALUES (?,?,?,?)
        ")->execute([$levId, $datum, $tijd, $gebruiker['idGebruiker']]);

        $id = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare("
            INSERT INTO Levering_has_Product (Levering_idLevering, Product_idProduct, Aantal)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE Aantal = Aantal + VALUES(Aantal)
        ");
        $bij = $pdo->prepare("UPDATE Product SET Aantal_voorraad = Aantal_voorraad + ? WHERE idProduct = ?");

        foreach ($regels as $r) {
            $pid = (int) ($r['product'] ?? 0);
            $aan = (int) ($r['aantal']  ?? 0);
            if ($pid <= 0 || $aan <= 0) { continue; }
            $ins->execute([$id, $pid, $aan]);
            $bij->execute([$aan, $pid]);          // voorraad automatisch bij (US-LEV-05)
        }

        // eventueel de volgende leverdatum opschonen als die nu geweest is
        $pdo->prepare("
            UPDATE Leverancier SET Volgende_leverdatum = NULL, Volgende_levertijd = NULL
             WHERE idLeverancier = ? AND Volgende_leverdatum <= ?
        ")->execute([$levId, $datum]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_out(['ok' => true, 'id' => $id, 'melding' => 'Levering geboekt en voorraad bijgewerkt.']);
}

json_out(['fout' => 'Onbekende actie.'], 400);
