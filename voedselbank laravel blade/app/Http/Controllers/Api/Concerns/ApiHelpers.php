<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

trait ApiHelpers
{
    protected function auth(): AuthService
    {
        return app(AuthService::class);
    }

    protected function vereisLogin(): array
    {
        $g = $this->auth()->huidigeGebruiker();
        if ($g === null) {
            abort(response()->json(['fout' => 'Je bent niet (meer) ingelogd.', 'login_nodig' => true], 401));
        }
        return $g;
    }

    protected function vereisPermissie(string $permissie): array
    {
        $g = $this->vereisLogin();
        if (!in_array($permissie, $g['permissies'], true)) {
            abort(response()->json(['fout' => 'Je hebt geen rechten voor deze actie.'], 403));
        }
        return $g;
    }

    protected function json(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
