<?php
declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Voert een .sql-bestand uit op de database die in .env staat.
 *
 * De bestanden database/schema/01-schema.sql en 02-seed.sql komen
 * rechtstreeks uit het ERD van de casus. We laten de regels
 * "CREATE DATABASE" en "USE voedselbank" weg: welke database gebruikt
 * wordt, bepaalt Laravel al via .env (DB_DATABASE).
 */
final class SqlBestand
{
    public static function voerUit(string $pad): int
    {
        if (!is_file($pad)) {
            throw new RuntimeException('SQL-bestand niet gevonden: ' . $pad);
        }

        $sql = (string) file_get_contents($pad);
        $sql = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', $sql) ?? $sql;

        $aantal = 0;
        foreach (self::statements($sql) as $statement) {
            DB::unprepared($statement);
            $aantal++;
        }
        return $aantal;
    }

    /**
     * Knipt het bestand in losse statements. Commentaarregels (-- ...) gaan
     * eruit; de puntkomma aan het einde van een regel is de scheiding.
     *
     * @return string[]
     */
    private static function statements(string $sql): array
    {
        $regels = [];
        foreach (explode("\n", $sql) as $regel) {
            if (!preg_match('/^\s*--/', $regel)) {
                $regels[] = $regel;
            }
        }

        $uit = [];
        foreach (explode(";\n", implode("\n", $regels) . "\n") as $statement) {
            $statement = trim($statement, " \t\n\r;");
            if ($statement !== '') {
                $uit[] = $statement;
            }
        }
        return $uit;
    }
}
