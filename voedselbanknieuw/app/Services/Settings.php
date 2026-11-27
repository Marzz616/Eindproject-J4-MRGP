<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Leest instellingen uit de tabel Instelling (bijv. tht_waarschuwing_dagen). */
final class Settings
{
    private static array $cache = [];

    public static function get(string $sleutel, string $standaard = ''): string
    {
        if (!array_key_exists($sleutel, self::$cache)) {
            $rij = DB::selectOne('SELECT Waarde FROM Instelling WHERE Sleutel = ?', [$sleutel]);
            self::$cache[$sleutel] = $rij === null ? null : (string) $rij->Waarde;
        }
        return self::$cache[$sleutel] ?? $standaard;
    }

    public static function int(string $sleutel, int $standaard): int
    {
        return (int) self::get($sleutel, (string) $standaard);
    }
}
