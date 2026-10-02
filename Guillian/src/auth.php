<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

/**
 * Authenticatie en autorisatie.
 *
 * De sessie bevat alleen het gebruikers-id. Rol en rechten worden bij
 * elk verzoek opnieuw uit de database gehaald, zodat een gebruiker die
 * geblokkeerd wordt of een andere rol krijgt dat direct merkt — ook als
 * hij nog een open sessie heeft.
 */

const SESSIE_DUUR = 3600; // 1 uur inactief -> uitloggen

function sessie_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,           // niet leesbaar vanuit JavaScript
        'samesite' => 'Strict',       // beschermt tegen CSRF
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('VBSESSID');
    session_start();

    // sessie verloopt na een uur zonder activiteit
    if (isset($_SESSION['laatste_actie']) && time() - $_SESSION['laatste_actie'] > SESSIE_DUUR) {
        sessie_vernietig();
        session_start();
    }
    $_SESSION['laatste_actie'] = time();
}

function sessie_vernietig(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    session_destroy();
}

/**
 * De ingelogde gebruiker, of null. Haalt rol en permissies vers op.
 */
function huidige_gebruiker(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache ?: null;
    }

    sessie_start();
    $id = $_SESSION['gebruiker_id'] ?? null;
    if (!$id) {
        $cache = false;
        return null;
    }

    $q = db()->prepare("
        SELECT g.idGebruiker, g.Naam, g.Gebruikersnaam, g.Email, g.Actief,
               g.Laatste_login, r.idRol, r.Naam AS RolNaam
          FROM Gebruiker g
          JOIN Rol r ON r.idRol = g.Rol_idRol
         WHERE g.idGebruiker = ?
    ");
    $q->execute([$id]);
    $g = $q->fetch();

    // account verwijderd of geblokkeerd terwijl de sessie liep
    if (!$g || !$g['Actief']) {
        sessie_vernietig();
        $cache = false;
        return null;
    }

    $p = db()->prepare("
        SELECT p.Code FROM Rol_has_Permissie rp
          JOIN Permissie p ON p.idPermissie = rp.Permissie_idPermissie
         WHERE rp.Rol_idRol = ?
    ");
    $p->execute([$g['idRol']]);

    $g['permissies'] = $p->fetchAll(PDO::FETCH_COLUMN);
    $cache = $g;
    return $g;
}

/** Heeft de ingelogde gebruiker dit recht? */
function mag(string $permissie): bool
{
    $g = huidige_gebruiker();
    return $g !== null && in_array($permissie, $g['permissies'], true);
}

function is_rol(string $rol): bool
{
    $g = huidige_gebruiker();
    return $g !== null && $g['RolNaam'] === $rol;
}

/**
 * Bewaakt een API: stopt het verzoek als er niemand is ingelogd of als
 * het recht ontbreekt. Dit is de serverkant van US-SEC-01 en US-SEC-02 —
 * knoppen verbergen in de frontend is niet genoeg.
 */
function vereis_login(): array
{
    $g = huidige_gebruiker();
    if ($g === null) {
        json_out(['fout' => 'Je bent niet (meer) ingelogd.', 'login_nodig' => true], 401);
    }
    return $g;
}

function vereis_permissie(string $permissie): array
{
    $g = vereis_login();
    if (!in_array($permissie, $g['permissies'], true)) {
        json_out(['fout' => 'Je hebt geen rechten voor deze actie.'], 403);
    }
    return $g;
}

/**
 * Vertaalt een permissie naar de pagina's die iemand mag openen.
 * Wordt gebruikt om de navigatie op te bouwen (US-SEC-01).
 */
function toegestane_paginas(array $g): array
{
    $p = $g['permissies'];
    $paginas = ['account'];                       // iedereen: wachtwoord wijzigen

    if (in_array('pakket_beheren', $p, true))     { $paginas[] = 'pakketten'; }
    if (in_array('pakket_uitgeven', $p, true))    { $paginas[] = 'uitgifte'; }
    if (in_array('product_beheren', $p, true) ||
        in_array('voorraad_corrigeren', $p, true)) { $paginas[] = 'producten'; }
    if (in_array('leverancier_beheren', $p, true) ||
        in_array('levering_boeken', $p, true))    { $paginas[] = 'leveranciers'; }
    if (in_array('klant_inzien', $p, true))       { $paginas[] = 'klanten'; }
    if (in_array('rapportage_export', $p, true))  { $paginas[] = 'rapportages'; $paginas[] = 'dashboard'; }
    if (in_array('gebruiker_beheren', $p, true))  { $paginas[] = 'gebruikers'; }

    return array_values(array_unique($paginas));
}

/**
 * Wat de frontend van de ingelogde gebruiker mag weten.
 * Nooit de wachtwoordhash meesturen.
 */
function gebruiker_publiek(array $g): array
{
    return [
        'id'         => (int) $g['idGebruiker'],
        'naam'       => $g['Naam'],
        'gnaam'      => $g['Gebruikersnaam'],
        'email'      => $g['Email'],
        'rol'        => $g['RolNaam'],
        'rolId'      => (int) $g['idRol'],
        'permissies' => $g['permissies'],
        'paginas'    => toegestane_paginas($g),
    ];
}
