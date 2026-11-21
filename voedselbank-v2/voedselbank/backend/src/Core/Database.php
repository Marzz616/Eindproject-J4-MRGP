<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Eén gedeelde databaseverbinding (PDO) voor de hele applicatie.
 *
 * - Prepared statements zonder emulatie: SQL-injectie is daardoor niet mogelijk.
 * - Fouten worden exceptions, zodat de Router ze centraal kan afhandelen.
 * - De tijdzone van MySQL wordt gelijk gezet aan die van PHP (Europe/Amsterdam),
 *   anders kloppen "vandaag" en "deze week" niet.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require __DIR__ . '/../../config/database.php';

        self::$pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']),
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        // MySQL kent "Europe/Amsterdam" alleen met geladen tijdzonetabellen;
        // de actuele offset (+01:00 / +02:00) werkt altijd, ook met zomertijd.
        self::$pdo->exec("SET time_zone = '" . (new DateTimeImmutable('now'))->format('P') . "'");

        return self::$pdo;
    }

    /**
     * Voert $werk uit binnen één transactie. Gaat er iets mis, dan wordt
     * alles teruggedraaid en wordt de fout doorgegooid.
     *
     * @template T
     * @param callable(PDO):T $werk
     * @return T
     */
    public static function transaction(callable $werk): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $uitkomst = $werk($pdo);
            $pdo->commit();
            return $uitkomst;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Maakt "?,?,?" voor een IN (...)-lijst. */
    public static function placeholders(array $waarden): string
    {
        return implode(',', array_fill(0, count($waarden), '?'));
    }
}
