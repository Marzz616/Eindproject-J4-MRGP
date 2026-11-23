<?php

namespace App\Http\Controllers\Api;

use App\Legacy\JsonExit;
use Illuminate\Http\Request;

class LegacyApiController
{
    public function handle(Request $request, string $script)
    {
        $script = preg_replace('/\.php$/', '', $script);

        $allowed = [
            'dashboard', 'gebruikers', 'klanten', 'leveranciers',
            'pakketten', 'producten', 'rapportages', 'uitgifte',
        ];
        if (!in_array($script, $allowed, true)) {
            return response()->json(['fout' => 'Onbekend endpoint: ' . $script], 404);
        }

        $_GET = array_merge($_GET ?? [], $request->query->all());

        require_once app_path('Legacy/helpers.php');

        $file = app_path('Legacy/api/' . $script . '.php');
        if (!is_file($file)) {
            return response()->json(['fout' => 'Script niet gevonden.'], 404);
        }

        try {
            (static function () use ($file) {
                require $file;
            })();
            return response()->json(['fout' => 'Geen antwoord van API.'], 500);
        } catch (JsonExit $e) {
            return response()->json($e->data, $e->status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'fout' => 'Er ging iets mis op de server.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
