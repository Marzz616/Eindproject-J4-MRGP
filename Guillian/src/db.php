<?php
declare(strict_types=1);

/** Eén centrale databaseverbinding (PDO). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'mysql';
    $name = getenv('DB_NAME') ?: 'voedselbank';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: 'password';

    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/** Stuurt JSON terug en stopt. */
function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Leest de JSON-body van een POST-verzoek. */
function json_in(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

/** Eén plek voor foutafhandeling: nooit een stacktrace naar de browser. */
function api_boot(): void
{
    set_exception_handler(function (Throwable $e): void {
        error_log('[api] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        json_out(['fout' => 'Er ging iets mis op de server.'], 500);
    });
}

/** Leest een instelling uit de tabel Instelling. */
function instelling(string $sleutel, string $standaard = ''): string
{
    $q = db()->prepare("SELECT Waarde FROM Instelling WHERE Sleutel = ?");
    $q->execute([$sleutel]);
    $w = $q->fetchColumn();
    return $w === false ? $standaard : (string) $w;
}

/** Verplicht POST; anders 405. */
function alleen_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_out(['fout' => 'Gebruik POST voor deze actie.'], 405);
    }
}