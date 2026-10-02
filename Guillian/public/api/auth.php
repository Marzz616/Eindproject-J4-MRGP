<?php
declare(strict_types=1);
require __DIR__ . '/../../src/auth.php';
api_boot();

/**
 * Authenticatie-endpoints.
 *   POST ?actie=login       {gebruikersnaam, wachtwoord}
 *   POST ?actie=logout
 *   GET  ?actie=ik          wie ben ik + rechten
 *   GET  ?actie=rechten      mijn eigen rechten met leesbare namen
 *   POST ?actie=wachtwoord  {huidig, nieuw, herhaal}   (US-GBR-04)
 */

$actie = $_GET['actie'] ?? 'ik';

// ---------------------------------------------------------------
if ($actie === 'ik') {
    $g = huidige_gebruiker();
    if ($g === null) {
        json_out(['ingelogd' => false], 200);
    }
    json_out(['ingelogd' => true, 'gebruiker' => gebruiker_publiek($g)]);
}

// ---------------------------------------------------------------
if ($actie === 'login') {
    alleen_post();
    sessie_start();

    $in    = json_in();
    $gnaam = trim((string) ($in['gebruikersnaam'] ?? ''));
    $pw    = (string) ($in['wachtwoord'] ?? '');

    if ($gnaam === '' || $pw === '') {
        json_out(['fout' => 'Vul je gebruikersnaam en wachtwoord in.'], 422);
    }

    // op gebruikersnaam OF e-mailadres kunnen inloggen
    $q = db()->prepare("
        SELECT g.idGebruiker, g.Naam, g.Wachtwoord_hash, g.Actief, g.Mislukte_pogingen
          FROM Gebruiker g
         WHERE g.Gebruikersnaam = ? OR g.Email = ?
    ");
    $q->execute([$gnaam, $gnaam]);
    $g = $q->fetch();

    // Bewust dezelfde melding bij onbekende gebruiker en fout wachtwoord:
    // anders kun je raden welke accounts bestaan.
    $fout = ['fout' => 'Gebruikersnaam of wachtwoord is onjuist.'];

    if (!$g) {
        usleep(300000);
        json_out($fout, 401);
    }

    if (!$g['Actief']) {
        json_out(['fout' => 'Je account is geblokkeerd. Neem contact op met de directie.'], 403);
    }

    if ((int) $g['Mislukte_pogingen'] >= 10) {
        json_out(['fout' => 'Te veel mislukte pogingen. Neem contact op met de directie.'], 429);
    }

    if (!password_verify($pw, $g['Wachtwoord_hash'])) {
        db()->prepare("UPDATE Gebruiker SET Mislukte_pogingen = Mislukte_pogingen + 1 WHERE idGebruiker = ?")
            ->execute([$g['idGebruiker']]);
        usleep(300000);
        json_out($fout, 401);
    }

    // hash verversen als PHP inmiddels een sterkere standaard heeft
    if (password_needs_rehash($g['Wachtwoord_hash'], PASSWORD_BCRYPT)) {
        db()->prepare("UPDATE Gebruiker SET Wachtwoord_hash = ? WHERE idGebruiker = ?")
            ->execute([password_hash($pw, PASSWORD_BCRYPT), $g['idGebruiker']]);
    }

    session_regenerate_id(true);           // tegen session fixation
    $_SESSION['gebruiker_id']  = (int) $g['idGebruiker'];
    $_SESSION['laatste_actie'] = time();

    db()->prepare("UPDATE Gebruiker SET Laatste_login = NOW(), Mislukte_pogingen = 0 WHERE idGebruiker = ?")
        ->execute([$g['idGebruiker']]);

    $vol = huidige_gebruiker();
    json_out(['ok' => true, 'gebruiker' => gebruiker_publiek($vol)]);
}

// ---------------------------------------------------------------
if ($actie === 'logout') {
    alleen_post();
    sessie_start();
    sessie_vernietig();
    json_out(['ok' => true]);
}

// ---------------------------------------------------------------
if ($actie === 'rechten') {         // mijn eigen rechten, met nette namen
    $g = vereis_login();
    if (!$g['permissies']) {
        json_out(['rechten' => []]);
    }
    $gaps = implode(',', array_fill(0, count($g['permissies']), '?'));
    $q = db()->prepare("SELECT Code, Naam FROM Permissie WHERE Code IN ($gaps) ORDER BY idPermissie");
    $q->execute($g['permissies']);
    json_out(['rechten' => $q->fetchAll()]);
}

// ---------------------------------------------------------------
if ($actie === 'wachtwoord') {      // US-GBR-04
    alleen_post();
    $g = vereis_login();

    $in      = json_in();
    $huidig  = (string) ($in['huidig']  ?? '');
    $nieuw   = (string) ($in['nieuw']   ?? '');
    $herhaal = (string) ($in['herhaal'] ?? '');

    $q = db()->prepare("SELECT Wachtwoord_hash FROM Gebruiker WHERE idGebruiker = ?");
    $q->execute([$g['idGebruiker']]);
    $hash = (string) $q->fetchColumn();

    $fouten = [];
    if (!password_verify($huidig, $hash))  { $fouten['huidig']  = 'Je huidige wachtwoord klopt niet.'; }
    if (mb_strlen($nieuw) < 8)             { $fouten['nieuw']   = 'Gebruik minimaal 8 tekens.'; }
    if (!preg_match('/[A-Za-z]/', $nieuw) || !preg_match('/\d/', $nieuw)) {
        $fouten['nieuw'] = 'Gebruik minimaal 8 tekens met letters én cijfers.';
    }
    if ($nieuw !== $herhaal)               { $fouten['herhaal'] = 'De twee wachtwoorden zijn niet gelijk.'; }
    if ($nieuw !== '' && $nieuw === $huidig) {
        $fouten['nieuw'] = 'Kies een ander wachtwoord dan je huidige.';
    }

    if ($fouten) {
        json_out(['fouten' => $fouten], 422);
    }

    db()->prepare("UPDATE Gebruiker SET Wachtwoord_hash = ? WHERE idGebruiker = ?")
        ->execute([password_hash($nieuw, PASSWORD_BCRYPT), $g['idGebruiker']]);

    json_out(['ok' => true]);
}

json_out(['fout' => 'Onbekende actie.'], 400);
