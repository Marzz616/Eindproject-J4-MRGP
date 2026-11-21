<?php
declare(strict_types=1);

namespace App\Auth;

/**
 * De PHP-sessie. Bevat alleen het gebruikers-id en het tijdstip van de
 * laatste actie; rol en rechten worden per verzoek vers uit de database gehaald.
 */
final class Session
{
    /** Na een uur niets doen word je automatisch uitgelogd. */
    public const MAX_INACTIEF = 3600;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,                      // niet leesbaar vanuit JavaScript
            'samesite' => 'Strict',                  // beschermt tegen CSRF
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_name('VBSESSID');
        session_start();

        if (isset($_SESSION['laatste_actie']) && time() - $_SESSION['laatste_actie'] > self::MAX_INACTIEF) {
            self::destroy();
            session_start();
        }
        $_SESSION['laatste_actie'] = time();
    }

    public static function userId(): ?int
    {
        self::start();
        return isset($_SESSION['gebruiker_id']) ? (int) $_SESSION['gebruiker_id'] : null;
    }

    public static function login(int $gebruikerId): void
    {
        $hadSessie = isset($_COOKIE[session_name()]) || isset($_COOKIE['VBSESSID']);
        self::start();
        // Tegen session fixation: stuurde de browser al een sessie-ID mee, dan
        // krijgt hij nu een nieuwe. Een verse sessie is al nieuw; nog eens
        // vernieuwen zou alleen een tweede Set-Cookie-header opleveren.
        if ($hadSessie) {
            session_regenerate_id(true);
        }
        $_SESSION['gebruiker_id']  = $gebruikerId;
        $_SESSION['laatste_actie'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
