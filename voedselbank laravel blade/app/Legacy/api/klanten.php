<?php
declare(strict_types=1);
/**
 * Klanten en gezinnen.
 *   GET  ?actie=lijst                US-KLT-03/11
 *   GET  ?actie=wensen               keuzelijst dieetwensen
 *   GET  ?actie=pakketten&klant=..   US-KLT-13/14
 *   POST ?actie=opslaan              US-KLT-01/02/04..10
 *   POST ?actie=status               US-KLT-12
 *   POST ?actie=verwijderen          US-KLT-01 (alleen zonder pakkethistorie)
 *   POST ?actie=wens                 US-KLT-10 (eigen wens toevoegen)
 */

$actie = $_GET['actie'] ?? 'lijst';
$pdo   = db();

/** Contactgegevens zijn privacygevoelig (US-SEC-02): alleen met klant_beheren. */
function mag_contact(): bool { return mag('klant_beheren'); }

// ---------------------------------------------------------------
if ($actie === 'lijst') {
    vereis_permissie('klant_inzien');

    $klanten = $pdo->query("
        SELECT k.idKlant, k.Klantnummer, k.Gezinsnaam, k.Adres, k.Postcode, k.Plaats,
               k.Telefoon, k.Email, k.Aanmelddatum, k.Geaccepteerd, k.Opmerkingen,
               k.Gezinsgrootte, k.Laatste_uitgifte,
               (SELECT COUNT(*) FROM Pakket p
                 WHERE p.Klant_idKlant = k.idKlant
                   AND p.Samenstellingsdatum >= (CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY)
               ) AS PakketDezeWeek
          FROM vw_Klant k
      ORDER BY k.Gezinsnaam
    ")->fetchAll();

    if (!$klanten) {
        json_out(['klanten' => [], 'rechten' => ['beheren' => mag('klant_beheren'), 'contact' => mag_contact()]]);
    }

    $ids  = array_column($klanten, 'idKlant');
    $gaps = implode(',', array_fill(0, count($ids), '?'));

    $leden = $pdo->prepare("
        SELECT idGezinslid, Naam, Geboortedatum, Relatie, Klant_idKlant, Leeftijd, Type
          FROM vw_Gezinslid
         WHERE Klant_idKlant IN ($gaps)
      ORDER BY FIELD(Relatie,'Hoofdaanvrager','Ouder (Vader)','Ouder (Moeder)','Partner','Kind','Inwonend'),
               Geboortedatum
    ");
    $leden->execute($ids);
    $leden = $leden->fetchAll();

    $wensen = $pdo->prepare("
        SELECT gw.Gezinslid_idGezinslid AS lid, w.idWens, w.Naam, w.Streng,
               gw.Toch_toestaan, gw.Toelichting
          FROM Gezinslid_has_Wens gw
          JOIN Wens w      ON w.idWens = gw.Wens_idWens
          JOIN Gezinslid g ON g.idGezinslid = gw.Gezinslid_idGezinslid
         WHERE g.Klant_idKlant IN ($gaps)
      ORDER BY w.Naam
    ");
    $wensen->execute($ids);

    $perLid = [];
    foreach ($wensen->fetchAll() as $r) {
        $perLid[(int) $r['lid']][] = [
            'id'           => (int) $r['idWens'],
            'naam'         => $r['Naam'],
            'streng'       => (bool) $r['Streng'],
            'uitzondering' => (int) $r['Toch_toestaan'] === 1,
            'toelichting'  => $r['Toelichting'],
        ];
    }

    $perKlant = [];
    foreach ($leden as $l) {
        $perKlant[(int) $l['Klant_idKlant']][] = [
            'id'       => (int) $l['idGezinslid'],
            'naam'     => $l['Naam'],
            'gb'       => $l['Geboortedatum'],
            'relatie'  => $l['Relatie'],
            'leeftijd' => (int) $l['Leeftijd'],
            'type'     => $l['Type'],
            'wensen'   => $perLid[(int) $l['idGezinslid']] ?? [],
        ];
    }

    $contact = mag_contact();
    $uit = [];
    foreach ($klanten as $k) {
        $id  = (int) $k['idKlant'];
        $rij = [
            'id'            => $id,
            'nummer'        => $k['Klantnummer'],
            'naam'          => $k['Gezinsnaam'],
            'aanmeld'       => $k['Aanmelddatum'],
            'status'        => $k['Geaccepteerd'] === null ? null : (bool) $k['Geaccepteerd'],
            'laatste'       => $k['Laatste_uitgifte'],
            'grootte'       => (int) $k['Gezinsgrootte'],
            'pakketDezeWeek'=> (int) $k['PakketDezeWeek'] > 0,
            'opmerkingen'   => $k['Opmerkingen'],
            'leden'         => $perKlant[$id] ?? [],
        ];
        // privacygevoelige velden alleen voor wie ze mag zien
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

    json_out([
        'klanten' => $uit,
        'rechten' => ['beheren' => mag('klant_beheren'), 'contact' => $contact],
    ]);
}

// ---------------------------------------------------------------
if ($actie === 'wensen') {
    vereis_permissie('klant_inzien');
    $rijen = $pdo->query("SELECT idWens, Naam, Streng FROM Wens ORDER BY Streng DESC, Naam")->fetchAll();
    json_out(array_map(fn($r) => [
        'id'     => (int) $r['idWens'],
        'naam'   => $r['Naam'],
        'streng' => (bool) $r['Streng'],
    ], $rijen));
}

// ---------------------------------------------------------------
if ($actie === 'wens') {                 // US-KLT-10
    alleen_post();
    vereis_permissie('klant_inzien');

    $in     = json_in();
    $naam   = trim((string) ($in['naam'] ?? ''));
    $streng = (bool) ($in['streng'] ?? false);
    $tag    = trim((string) ($in['tag'] ?? ''));

    if (mb_strlen($naam) < 2) {
        json_out(['fout' => 'Geef een omschrijving van de wens.'], 422);
    }

    $q = $pdo->prepare("SELECT idWens, Naam, Streng FROM Wens WHERE Naam = ?");
    $q->execute([$naam]);
    if ($r = $q->fetch()) {
        json_out(['id' => (int) $r['idWens'], 'naam' => $r['Naam'], 'streng' => (bool) $r['Streng']]);
    }

    // Zonder conflict-tag waarschuwt de applicatie nergens voor; de wens is dan
    // puur informatief voor de vrijwilliger die het pakket samenstelt.
    $pdo->prepare("
        INSERT INTO Wens (Naam, Omschrijving, Streng, Conflict_tag)
        VALUES (?, 'Eigen wens, ingevoerd bij een klant', ?, ?)
    ")->execute([$naam, $streng ? 1 : 0, $tag !== '' ? mb_strtolower($tag) : null]);

    json_out(['id' => (int) $pdo->lastInsertId(), 'naam' => $naam, 'streng' => $streng], 201);
}

// ---------------------------------------------------------------
if ($actie === 'pakketten') {            // US-KLT-13/14
    vereis_permissie('klant_inzien');
    $klant = (int) ($_GET['klant'] ?? 0);
    if ($klant <= 0) { json_out(['fout' => 'Geef een klant op.'], 422); }

    $pk = $pdo->prepare("
        SELECT idPakket, Samenstellingsdatum, Uitgiftedatum, Opgehaald, Status
          FROM Pakket WHERE Klant_idKlant = ?
      ORDER BY Samenstellingsdatum DESC
    ");
    $pk->execute([$klant]);
    $pk = $pk->fetchAll();
    if (!$pk) { json_out([]); }

    $ids  = array_column($pk, 'idPakket');
    $gaps = implode(',', array_fill(0, count($ids), '?'));
    $rg = $pdo->prepare("
        SELECT pp.Pakket_idPakket AS pakket, p.Productnaam, pp.Aantal
          FROM Product_has_Pakket pp
          JOIN Product p ON p.idProduct = pp.Product_idProduct
         WHERE pp.Pakket_idPakket IN ($gaps)
      ORDER BY p.Productnaam
    ");
    $rg->execute($ids);

    $per = [];
    foreach ($rg->fetchAll() as $r) {
        $per[(int) $r['pakket']][] = ['product' => $r['Productnaam'], 'aantal' => (int) $r['Aantal']];
    }

    json_out(array_map(fn($p) => [
        'id'        => (int) $p['idPakket'],
        'samen'     => $p['Samenstellingsdatum'],
        'uitgifte'  => $p['Uitgiftedatum'],
        'status'    => $p['Status'],
        'opgehaald' => (bool) $p['Opgehaald'],
        'regels'    => $per[(int) $p['idPakket']] ?? [],
    ], $pk));
}

// ---------------------------------------------------------------
if ($actie === 'opslaan') {               // US-KLT-01/02/04..10
    alleen_post();
    $g = vereis_permissie('klant_inzien');

    $in    = json_in();
    $id    = (int) ($in['id'] ?? 0);
    $leden = is_array($in['leden'] ?? null) ? $in['leden'] : [];

    // Een vrijwilliger mag alleen gezinssamenstelling en wensen bijwerken
    // (US-KLT-05..10), geen contactgegevens en geen nieuwe klanten.
    $volledig = mag('klant_beheren');
    if (!$volledig && $id === 0) {
        json_out(['fout' => 'Alleen de directie kan nieuwe klanten registreren.'], 403);
    }

    $fouten = [];
    if (!$leden) { $fouten['leden'] = 'Voeg minimaal één gezinslid toe.'; }

    if ($volledig) {
        $naam     = trim((string) ($in['naam'] ?? ''));
        $adres    = trim((string) ($in['adres'] ?? ''));
        $postcode = strtoupper(str_replace(' ', '', (string) ($in['postcode'] ?? '')));
        $plaats   = trim((string) ($in['plaats'] ?? 'Maaskantje')) ?: 'Maaskantje';
        $telefoon = trim((string) ($in['telefoon'] ?? ''));
        $email    = trim((string) ($in['email'] ?? ''));
        $opm      = mb_substr(trim((string) ($in['opmerkingen'] ?? '')), 0, 500);

        if (mb_strlen($naam) < 2)                                { $fouten['naam']     = 'Gezinsnaam is verplicht.'; }
        if (mb_strlen($adres) < 3)                               { $fouten['adres']    = 'Adres is verplicht.'; }
        if (!preg_match('/^[1-9][0-9]{3}[A-Z]{2}$/', $postcode)) { $fouten['postcode'] = 'Bijvoorbeeld 5492 AB.'; }
        if (mb_strlen($telefoon) < 9)                            { $fouten['telefoon'] = 'Telefoonnummer is verplicht.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))          { $fouten['email']    = 'Vul een geldig e-mailadres in.'; }
    }

    if ($fouten) { json_out(['fouten' => $fouten], 422); }

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            if ($volledig) {
                $pdo->prepare("
                    UPDATE Klant SET Gezinsnaam=?, Adres=?, Postcode=?, Plaats=?, Telefoon=?,
                           Email=?, Opmerkingen=?
                     WHERE idKlant=?
                ")->execute([$naam, $adres, $postcode, $plaats, $telefoon, $email, $opm ?: null, $id]);
            }
            $pdo->prepare("DELETE FROM Gezinslid WHERE Klant_idKlant = ?")->execute([$id]);
        } else {
            $pdo->prepare("
                INSERT INTO Klant (Gezinsnaam, Adres, Postcode, Plaats, Telefoon, Email,
                                   Opmerkingen, Aanmelddatum, Geaccepteerd)
                VALUES (?,?,?,?,?,?,?,CURDATE(),NULL)
            ")->execute([$naam, $adres, $postcode, $plaats, $telefoon, $email, $opm ?: null]);
            $id = (int) $pdo->lastInsertId();
        }

        $insLid  = $pdo->prepare("INSERT INTO Gezinslid (Naam, Geboortedatum, Relatie, Klant_idKlant) VALUES (?,?,?,?)");
        $insWens = $pdo->prepare("
            INSERT IGNORE INTO Gezinslid_has_Wens
                (Gezinslid_idGezinslid, Wens_idWens, Toch_toestaan, Toelichting)
            VALUES (?,?,?,?)
        ");

        foreach ($leden as $lid) {
            $ln = trim((string) ($lid['naam'] ?? ''));
            $gb = (string) ($lid['gb'] ?? '');
            if ($ln === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gb)) { continue; }

            $rel = (string) ($lid['relatie'] ?? 'Kind');
            $insLid->execute([$ln, $gb, $rel, $id]);
            $lidId = (int) $pdo->lastInsertId();

            // Een uitzondering telt alleen mee als er een reden bij staat;
            // zonder uitleg blijft de blokkade gewoon gelden.
            $uitz = (array) ($lid['uitzonderingen'] ?? []);
            foreach ((array) ($lid['wensen'] ?? []) as $w) {
                $wid  = (int) $w;
                $reden = trim((string) ($uitz[$wid] ?? ''));
                $insWens->execute([$lidId, $wid, $reden !== '' ? 1 : 0, $reden !== '' ? $reden : null]);
            }
        }

        $pdo->commit();
        json_out(['ok' => true, 'id' => $id, 'melding' => 'Klantgegevens opgeslagen.']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ---------------------------------------------------------------
if ($actie === 'verwijderen') {            // US-KLT-01 ("klanten beheren")
    alleen_post();
    vereis_permissie('klant_beheren');     // vrijwilliger krijgt 403

    $id = (int) (json_in()['id'] ?? 0);
    if ($id <= 0) { json_out(['fout' => 'Onbekende klant.'], 422); }

    $q = $pdo->prepare("
        SELECT k.Gezinsnaam,
               (SELECT COUNT(*) FROM Pakket p WHERE p.Klant_idKlant = k.idKlant) AS Pakketten
          FROM Klant k WHERE k.idKlant = ?
    ");
    $q->execute([$id]);
    $k = $q->fetch();
    if (!$k) { json_out(['fout' => 'Klant niet gevonden.'], 404); }

    // Historie beschermen: een gezin dat ooit een pakket kreeg blijft staan,
    // anders kloppen de rapportages niet meer.
    $aantal = (int) $k['Pakketten'];
    if ($aantal > 0) {
        json_out([
            'fout'      => $k['Gezinsnaam'] . ' heeft ' . $aantal . ' pakket'
                         . ($aantal === 1 ? '' : 'ten') . ' ontvangen. '
                         . 'Die geschiedenis blijft bewaard voor de rapportages.',
            'reden'     => 'heeft_historie',
            'aantal'    => $aantal,
            'klant'     => $k['Gezinsnaam'],
            'suggestie' => 'Zet de klant op non-actief. Hij verdwijnt dan uit de keuzelijsten '
                         . 'bij het samenstellen, maar de pakketten blijven in de rapportages staan.',
        ], 409);
    }

    // Geen historie: weg mag. Gezinsleden en hun wensen gaan mee via
    // ON DELETE CASCADE op Gezinslid -> Klant en Gezinslid_has_Wens -> Gezinslid.
    $pdo->prepare("DELETE FROM Klant WHERE idKlant = ?")->execute([$id]);

    json_out([
        'ok'      => true,
        'melding' => $k['Gezinsnaam'] . ' is verwijderd, inclusief de gezinsleden en hun dieetwensen.',
    ]);
}

// ---------------------------------------------------------------
if ($actie === 'status') {                 // US-KLT-12
    alleen_post();
    vereis_permissie('klant_beheren');

    $in = json_in();
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) { json_out(['fout' => 'Onbekende klant.'], 422); }

    $waarde = array_key_exists('geaccepteerd', $in) && $in['geaccepteerd'] !== null
        ? (int) (bool) $in['geaccepteerd'] : null;

    $c = $pdo->prepare("SELECT Gezinsnaam FROM Klant WHERE idKlant = ?");
    $c->execute([$id]);
    $naam = $c->fetchColumn();
    if ($naam === false) { json_out(['fout' => 'Klant niet gevonden.'], 404); }

    $pdo->prepare("UPDATE Klant SET Geaccepteerd = ? WHERE idKlant = ?")->execute([$waarde, $id]);

    json_out([
        'ok'      => true,
        'melding' => $waarde === 1
            ? $naam . ' is geaccepteerd en kan pakketten ontvangen.'
            : ($waarde === 0 ? $naam . ' staat nu op non-actief.' : $naam . ' wacht weer op beoordeling.'),
    ]);
}

json_out(['fout' => 'Onbekende actie.'], 400);
