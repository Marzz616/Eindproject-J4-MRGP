<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * Basis voor alle models. Een model bevat alleen databasewerk (SQL);
 * de controller beslist wat ermee gebeurt en wat er terug naar de browser gaat.
 *
 * De SQL loopt via de databaselaag van Laravel (de DB-facade). Laravel gebruikt
 * daaronder PDO met prepared statements: de waarden gaan apart mee als "?",
 * dus SQL-injectie is niet mogelijk.
 *
 * Laravel geeft rijen terug als objecten; wij maken er arrays van, omdat de
 * rest van de applicatie met arrays werkt ($rij['Naam']).
 */
abstract class Basis
{
    /** Alle rijen van een query. */
    protected static function all(string $sql, array $params = []): array
    {
        return array_map(static fn(object $rij): array => (array) $rij, DB::select($sql, $params));
    }

    /** Eén rij, of null. */
    protected static function one(string $sql, array $params = []): ?array
    {
        $rijen = self::all($sql, $params);
        return $rijen[0] ?? null;
    }

    /** Eén waarde (eerste kolom van de eerste rij), of null. */
    protected static function value(string $sql, array $params = []): mixed
    {
        $rij = self::one($sql, $params);
        return $rij === null ? null : array_values($rij)[0] ?? null;
    }

    /** Eén kolom als lijst. */
    protected static function column(string $sql, array $params = []): array
    {
        return array_map(static fn(array $rij): mixed => array_values($rij)[0] ?? null, self::all($sql, $params));
    }

    /** INSERT/UPDATE/DELETE; geeft het aantal geraakte rijen terug. */
    protected static function execute(string $sql, array $params = []): int
    {
        return DB::affectingStatement($sql, $params);
    }

    /** Het id van de zojuist toegevoegde rij. */
    protected static function lastId(): int
    {
        return (int) DB::getPdo()->lastInsertId();
    }

    /** Maakt "?,?,?" voor een IN (...)-lijst. */
    protected static function plaatshouders(array $waarden): string
    {
        return implode(',', array_fill(0, count($waarden), '?'));
    }
}
