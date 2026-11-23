<?php
/**
 * Bridge: originele public/api/*.php-logica binnen Laravel.
 */

namespace App\Legacy {
    class JsonExit extends \Exception
    {
        public function __construct(public mixed $data, public int $status = 200)
        {
            parent::__construct('json_out');
        }
    }
}

namespace {
    use App\Legacy\JsonExit;
    use App\Services\AuthService;
    use Illuminate\Support\Facades\DB;

    function db(): \PDO
    {
        return DB::connection()->getPdo();
    }

    function json_out(mixed $data, int $status = 200): never
    {
        throw new JsonExit($data, $status);
    }

    function json_in(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        return is_array($data) ? $data : [];
    }

    function api_boot(): void
    {
    }

    function alleen_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_out(['fout' => 'Gebruik POST voor deze actie.'], 405);
        }
    }

    function instelling(string $sleutel, string $standaard = ''): string
    {
        $q = db()->prepare("SELECT Waarde FROM Instelling WHERE Sleutel = ?");
        $q->execute([$sleutel]);
        $w = $q->fetchColumn();
        return $w === false ? $standaard : (string) $w;
    }

    function sessie_start(): void
    {
        app(AuthService::class)->startSessie();
    }

    function sessie_vernietig(): void
    {
        app(AuthService::class)->vernietigSessie();
    }

    function huidige_gebruiker(): ?array
    {
        return app(AuthService::class)->huidigeGebruiker();
    }

    function mag(string $permissie): bool
    {
        return app(AuthService::class)->mag($permissie);
    }

    function is_rol(string $rol): bool
    {
        $g = huidige_gebruiker();
        return $g !== null && $g['RolNaam'] === $rol;
    }

    function vereis_login(): array
    {
        $g = huidige_gebruiker();
        if ($g === null) {
            json_out(['fout' => 'Je bent niet (meer) ingelogd.', 'login_nodig' => true], 401);
        }
        return $g;
    }

    function vereis_permissie(string $permissie): array
    {
        $g = vereis_login();
        if (!in_array($permissie, $g['permissies'], true)) {
            json_out(['fout' => 'Je hebt geen rechten voor deze actie.'], 403);
        }
        return $g;
    }

    function toegestane_paginas(array $g): array
    {
        return app(AuthService::class)->toegestanePaginas($g);
    }

    function gebruiker_publiek(array $g): array
    {
        return app(AuthService::class)->gebruikerPubliek($g);
    }
}
