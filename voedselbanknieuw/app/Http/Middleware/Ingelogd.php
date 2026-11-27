<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laat alleen ingelogde gebruikers door. Niet ingelogd: 401 met
 * {"fout": "...", "login_nodig": true}, waarop de frontend het
 * inlogscherm toont.
 *
 * In routes/api.php staat deze middleware als 'ingelogd'.
 */
final class Ingelogd
{
    public function handle(Request $request, Closure $next): Response
    {
        AuthService::requireLogin();

        return $next($request);
    }
}
