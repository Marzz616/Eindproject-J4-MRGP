<?php
declare(strict_types=1);
/**
 * Gebruikers & Toegangsbeheer — US-GBR-01 t/m US-GBR-09, US-SEC-03.
 *
 *   GET  ?actie=lijst        gebruikers, rollen en de rechten per rol
 *   POST ?actie=opslaan      account toevoegen of wijzigen
 *   POST ?actie=blokkeren    account blokkeren / deblokkeren
 *   POST ?actie=reset        nieuw wachtwoord instellen voor iemand anders
 */

$actie = $_GET['actie'] ?? 'lijst';

/** Hoeveel actieve directieleden zijn er nog (los van deze gebruiker)? */
function andere_actieve_directie(int $behalveId): int
{
    $q = db()->prepare("
        SELECT COUNT(*) FROM Gebruiker g JOIN Rol r ON r.idRol = g.Rol_idRol
         WHERE r.Naam = 'Directie' AND g.Actief = 1 AND g.idGebruiker <> ?
    ");
    $q->execute([$behalveId]);
    return (int) $q->fetchColumn();
}

/* ------------------------------------------------------------------ */
if ($actie === 'lijst') {
    vereis_permissie('gebruiker_beheren');

    $gebruikers = db()->query("
        SELECT g.idGebruiker, g.Naam, g.Gebruikersnaam, g.Email, g.Actief,
               g.Laatste_login, g.Mislukte_pogingen, r.idRol, r.Naam AS Rol
          FROM Gebruiker g JOIN Rol r ON r.idRol = g.Rol_idRol
         ORDER BY g.Actief DESC, g.Naam
    ")->fetchAll();

    $rollen = db()->query("SELECT idRol, Naam, Omschrijving FROM Rol ORDER BY idRol")->fetchAll();

    $permissies = db()->query("SELECT idPermissie, Code, Naam FROM Permissie ORDER BY idPermissie")->fetchAll();

    $koppeling = [];
    foreach (db()->query("SELECT Rol_idRol, Permissie_idPermissie FROM Rol_has_Permissie")->fetchAll() as $r) {
        $koppeling[(int) $r['Rol_idRol']][] = (int) $r['Permissie_idPermissie'];
    }

    json_out([
        'gebruikers' => array_map(static fn(array $g): array => [
            'id'         => (int) $g['idGebruiker'],
            'naam'       => $g['Naam'],
            'gnaam'      => $g['Gebruikersnaam'],
            'email'      => $g['Email'],
            'rol'        => $g['Rol'],
            'rolId'      => (int) $g['idRol'],
            'actief'     => (bool) $g['Actief'],
            'laatste'    => $g['Laatste_login'],
            'mislukt'    => (int) $g['Mislukte_pogingen'],
        ], $gebruikers),
        'rollen' => array_map(static fn(array $r): array => [
            'id'          => (int) $r['idRol'],
            'naam'        => $r['Naam'],
            'omschrijving'=> $r['Omschrijving'],
            'permissies'  => $koppeling[(int) $r['idRol']] ?? [],
        ], $rollen),
        'permissies' => array_map(static fn(array $p): array => [
            'id'   => (int) $p['idPermissie'],
            'code' => $p['Code'],
            'naam' => $p['Naam'],
        ], $permissies),
        'ik' => (int) huidige_gebruiker()['idGebruiker'],
    ]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'opslaan') {                      // US-GBR-01 / US-GBR-02
    alleen_post();
    $ik = vereis_permissie('gebruiker_beheren');

    $in    = json_in();
    $id    = (int) ($in['id'] ?? 0);
    $naam  = trim((string) ($in['naam']  ?? ''));
    $gnaam = trim((string) ($in['gnaam'] ?? ''));
    $email = trim((string) ($in['email'] ?? ''));
    $rolId = (int) ($in['rolId'] ?? 0);
    $pw    = (string) ($in['wachtwoord'] ?? '');

    $fouten = [];
    if ($naam === '')                              { $fouten['naam']  = 'Vul de naam in.'; }
    if (!preg_match('/^[a-z0-9._-]{3,60}$/i', $gnaam)) {
        $fouten['gnaam'] = 'Gebruik 3 tot 60 tekens: letters, cijfers, punt, streepje of underscore.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $fouten['email'] = 'Vul een geldig e-mailadres in.'; }

    $r = db()->prepare("SELECT Naam FROM Rol WHERE idRol = ?");
    $r->execute([$rolId]);
    $rolNaam = $r->fetchColumn();
    if ($rolNaam === false) { $fouten['rolId'] = 'Kies een rol.'; }

    // wachtwoordeisen (US-SEC-03)
    if ($id === 0 || $pw !== '') {
        if (mb_strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
            $fouten['wachtwoord'] = 'Gebruik minimaal 8 tekens met letters én cijfers.';
        }
    }

    // gebruikersnaam en e-mail moeten uniek blijven
    $q = db()->prepare("SELECT idGebruiker, Gebruikersnaam, Email FROM Gebruiker WHERE (Gebruikersnaam = ? OR Email = ?) AND idGebruiker <> ?");
    $q->execute([$gnaam, $email, $id]);
    foreach ($q->fetchAll() as $bestaand) {
        if (strcasecmp($bestaand['Gebruikersnaam'], $gnaam) === 0) { $fouten['gnaam'] = 'Deze gebruikersnaam bestaat al.'; }
        if (strcasecmp($bestaand['Email'], $email) === 0)          { $fouten['email'] = 'Dit e-mailadres is al in gebruik.'; }
    }

    if ($fouten) {
        json_out(['fouten' => $fouten], 422);
    }

    // de laatste directie mag zichzelf niet degraderen (US-GBR-05)
    if ($id > 0) {
        $q = db()->prepare("SELECT r.Naam FROM Gebruiker g JOIN Rol r ON r.idRol = g.Rol_idRol WHERE g.idGebruiker = ?");
        $q->execute([$id]);
        $huidigeRol = $q->fetchColumn();

        if ($huidigeRol === 'Directie' && $rolNaam !== 'Directie' && andere_actieve_directie($id) === 0) {
            json_out(['fout' => 'Dit is het laatste actieve directie-account. Maak eerst iemand anders directielid.'], 409);
        }
    }

    if ($id > 0) {
        db()->prepare("UPDATE Gebruiker SET Naam=?, Gebruikersnaam=?, Email=?, Rol_idRol=? WHERE idGebruiker=?")
            ->execute([$naam, $gnaam, $email, $rolId, $id]);

        if ($pw !== '') {
            db()->prepare("UPDATE Gebruiker SET Wachtwoord_hash=?, Mislukte_pogingen=0 WHERE idGebruiker=?")
                ->execute([password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
        }
        json_out(['ok' => true, 'id' => $id, 'melding' => 'Account bijgewerkt.']);
    }

    db()->prepare("
        INSERT INTO Gebruiker (Naam, Gebruikersnaam, Email, Wachtwoord_hash, Actief, Rol_idRol)
        VALUES (?,?,?,?,1,?)
    ")->execute([$naam, $gnaam, $email, password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), $rolId]);

    json_out([
        'ok'      => true,
        'id'      => (int) db()->lastInsertId(),
        'melding' => 'Account aangemaakt voor ' . $naam . '.',
    ]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'blokkeren') {                    // US-GBR-03
    alleen_post();
    $ik = vereis_permissie('gebruiker_beheren');

    $in  = json_in();
    $id  = (int) ($in['id'] ?? 0);
    $aan = (bool) ($in['actief'] ?? false);

    if ($id === (int) $ik['idGebruiker'] && !$aan) {
        json_out(['fout' => 'Je kunt je eigen account niet blokkeren.'], 409);
    }

    $q = db()->prepare("SELECT g.Naam, r.Naam AS Rol FROM Gebruiker g JOIN Rol r ON r.idRol = g.Rol_idRol WHERE g.idGebruiker = ?");
    $q->execute([$id]);
    $g = $q->fetch();
    if (!$g) {
        json_out(['fout' => 'Gebruiker niet gevonden.'], 404);
    }

    if (!$aan && $g['Rol'] === 'Directie' && andere_actieve_directie($id) === 0) {
        json_out(['fout' => 'Dit is het laatste actieve directie-account en kan niet geblokkeerd worden.'], 409);
    }

    // Accounts worden geblokkeerd, nooit verwijderd: wie een pakket heeft
    // samengesteld moet in de historie terug te vinden blijven.
    db()->prepare("UPDATE Gebruiker SET Actief = ?, Mislukte_pogingen = 0 WHERE idGebruiker = ?")
        ->execute([$aan ? 1 : 0, $id]);

    json_out([
        'ok'      => true,
        'melding' => $aan
            ? $g['Naam'] . ' kan weer inloggen.'
            : $g['Naam'] . ' is geblokkeerd en kan niet meer inloggen.',
    ]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'reset') {                        // US-GBR-04 (door de beheerder)
    alleen_post();
    vereis_permissie('gebruiker_beheren');

    $in = json_in();
    $id = (int) ($in['id'] ?? 0);
    $pw = (string) ($in['wachtwoord'] ?? '');

    if (mb_strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        json_out(['fouten' => ['wachtwoord' => 'Gebruik minimaal 8 tekens met letters én cijfers.']], 422);
    }

    $n = db()->prepare("UPDATE Gebruiker SET Wachtwoord_hash = ?, Mislukte_pogingen = 0 WHERE idGebruiker = ?");
    $n->execute([password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), $id]);

    if ($n->rowCount() === 0) {
        json_out(['fout' => 'Gebruiker niet gevonden.'], 404);
    }

    json_out(['ok' => true, 'melding' => 'Nieuw wachtwoord ingesteld.']);
}

json_out(['fout' => 'Onbekende actie.'], 400);
