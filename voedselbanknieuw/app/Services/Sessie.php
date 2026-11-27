<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Session as LaravelSessie;

/**
 * De sessie van de ingelogde gebruiker.
 *
 * Laravel regelt zelf het opslaan van de sessie en de cookie; wij bewaren
 * er alleen het gebruikers-id in. Rol en rechten worden bij elk verzoek vers
 * uit de database gehaald (zie AuthService), zodat blokkeren of een andere
 * rol direct werkt.
 *
 * De instellingen van de cookie staan in config/session.php:
 *   http_only = true      niet leesbaar vanuit JavaScript
 *   same_site = strict    de browser stuurt de cookie niet mee vanaf een
 *                         andere site, dat beschermt tegen CSRF
 *   lifetime  = 60        na een uur niets doen ben je uitgelogd
 */
final class Sessie
{
    public static function gebruikerId(): ?int
    {
        $id = LaravelSessie::get('gebruiker_id');
        return $id === null ? null : (int) $id;
    }

    /**
     * Inloggen. De sessie krijgt een nieuw ID (regenerate), zodat een
     * meegegeven sessie-ID niet overgenomen kan worden: session fixation.
     */
    public static function login(int $gebruikerId): void
    {
        LaravelSessie::regenerate();
        LaravelSessie::put('gebruiker_id', $gebruikerId);
    }

    /** Uitloggen: alles uit de sessie en een nieuw, leeg sessie-ID. */
    public static function destroy(): void
    {
        LaravelSessie::flush();
        LaravelSessie::regenerate();
    }
}
