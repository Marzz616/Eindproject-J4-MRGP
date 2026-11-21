<?php
declare(strict_types=1);

namespace App\Core;

/** Leest instellingen uit de tabel Instelling (bijv. tht_waarschuwing_dagen). */
final class Settings
{
    private static array $cache = [];

    public static function get(string $sleutel, string $standaard = ''): string
    {
        if (!array_key_exists($sleutel, self::$cache)) {
            $q = Database::connection()->prepare('SELECT Waarde FROM Instelling WHERE Sleutel = ?');
            $q->execute([$sleutel]);
            $waarde = $q->fetchColumn();
            self::$cache[$sleutel] = $waarde === false ? null : (string) $waarde;
        }
        return self::$cache[$sleutel] ?? $standaard;
    }

    public static function int(string $sleutel, int $standaard): int
    {
        return (int) self::get($sleutel, (string) $standaard);
    }
}
