<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Basis voor alle models. Een model bevat alleen databasewerk (SQL);
 * de controller beslist wat ermee gebeurt en wat er terug naar de browser gaat.
 */
abstract class Model
{
    protected static function db(): PDO
    {
        return Database::connection();
    }

    /** Alle rijen van een query. */
    protected static function all(string $sql, array $params = []): array
    {
        $q = self::db()->prepare($sql);
        $q->execute($params);
        return $q->fetchAll();
    }

    /** Eén rij, of null. */
    protected static function one(string $sql, array $params = []): ?array
    {
        $q = self::db()->prepare($sql);
        $q->execute($params);
        $rij = $q->fetch();
        return $rij === false ? null : $rij;
    }

    /** Eén waarde (eerste kolom van de eerste rij), of null. */
    protected static function value(string $sql, array $params = []): mixed
    {
        $q = self::db()->prepare($sql);
        $q->execute($params);
        $w = $q->fetchColumn();
        return $w === false ? null : $w;
    }

    /** Eén kolom als lijst. */
    protected static function column(string $sql, array $params = []): array
    {
        $q = self::db()->prepare($sql);
        $q->execute($params);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    }

    /** INSERT/UPDATE/DELETE; geeft het aantal geraakte rijen terug. */
    protected static function execute(string $sql, array $params = []): int
    {
        $q = self::db()->prepare($sql);
        $q->execute($params);
        return $q->rowCount();
    }

    protected static function lastId(): int
    {
        return (int) self::db()->lastInsertId();
    }
}
