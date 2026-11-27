<?php
declare(strict_types=1);

namespace App\Services;

/** Wachtwoordregels en hashing op één plek (US-SEC-03). */
final class Wachtwoord
{
    public const EIS = 'Gebruik minimaal 8 tekens met letters én cijfers.';

    public static function voldoet(string $wachtwoord): bool
    {
        return mb_strlen($wachtwoord) >= 8
            && preg_match('/[A-Za-z]/', $wachtwoord) === 1
            && preg_match('/\d/', $wachtwoord) === 1;
    }

    /** bcrypt met cost 12: traag genoeg om raden duur te maken. */
    public static function hash(string $wachtwoord): string
    {
        return password_hash($wachtwoord, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
