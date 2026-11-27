<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\Models\Gebruiker;

/**
 * Wie is er ingelogd en wat mag hij?
 *
 * Rol en permissies worden bij elk verzoek opnieuw uit de database gelezen.
 * Wordt iemand geblokkeerd of krijgt hij een andere rol, dan merkt hij dat
 * direct — ook met een sessie die nog openstaat.
 *
 * Dit is bewust een eigen service en niet de ingebouwde login van Laravel:
 * de database komt uit het ERD van de casus (tabellen Gebruiker, Rol,
 * Permissie) en niet uit het standaard users-schema van Laravel.
 */
final class AuthService
{
    private static ?array $gebruiker = null;
    private static bool $geladen = false;

    /** De ingelogde gebruiker met 'permissies', of null. */
    public static function user(): ?array
    {
        if (self::$geladen) {
            return self::$gebruiker;
        }
        self::$geladen = true;

        $id = Sessie::gebruikerId();
        if ($id === null) {
            return null;
        }

        $g = Gebruiker::metRol($id);
        if ($g === null || !$g['Actief']) {
            // account verwijderd of geblokkeerd terwijl de sessie liep
            Sessie::destroy();
            return null;
        }

        $g['permissies'] = Gebruiker::permissiesVanRol((int) $g['idRol']);
        return self::$gebruiker = $g;
    }

    public static function id(): int
    {
        return (int) (self::user()['idGebruiker'] ?? 0);
    }

    /** Heeft de ingelogde gebruiker deze permissie? */
    public static function can(string $permissie): bool
    {
        $g = self::user();
        return $g !== null && in_array($permissie, $g['permissies'], true);
    }

    public static function requireLogin(): array
    {
        $g = self::user();
        if ($g === null) {
            throw new HttpException(401, 'Je bent niet (meer) ingelogd.', ['login_nodig' => true]);
        }
        return $g;
    }

    /** Minstens één van de genoemde permissies is nodig, anders 403. */
    public static function requirePermission(string ...$permissies): array
    {
        $g = self::requireLogin();
        foreach ($permissies as $p) {
            if (in_array($p, $g['permissies'], true)) {
                return $g;
            }
        }
        throw new HttpException(403, 'Je hebt geen rechten voor deze actie.');
    }

    /** Na inloggen/uitloggen opnieuw laden. */
    public static function refresh(): void
    {
        self::$geladen = false;
        self::$gebruiker = null;
    }

    /** Wat de frontend over de ingelogde gebruiker mag weten — nooit de wachtwoordhash. */
    public static function publicProfile(array $g): array
    {
        return [
            'id'         => (int) $g['idGebruiker'],
            'naam'       => $g['Naam'],
            'gnaam'      => $g['Gebruikersnaam'],
            'email'      => $g['Email'],
            'rol'        => $g['RolNaam'],
            'rolId'      => (int) $g['idRol'],
            'permissies' => $g['permissies'],
            'paginas'    => Pages::allowedFor($g['permissies']),
        ];
    }
}
