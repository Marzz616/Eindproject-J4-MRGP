<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controleert of de ingelogde gebruiker de juiste permissie heeft.
 *
 *   Route::get('/klanten', ...)->middleware('mag:klant_inzien');
 *   Route::get('/producten', ...)->middleware('mag:product_beheren,voorraad_corrigeren');
 *
 * Bij meerdere codes is er één van nodig. Omdat de controle hier zit — bij de
 * route en dus op de server — kan een controller hem niet vergeten en helpt
 * het niet om in de browser een knop terug te toveren (US-SEC-01).
 */
final class Permissie
{
    public function handle(Request $request, Closure $next, string ...$permissies): Response
    {
        AuthService::requirePermission(...$permissies);

        return $next($request);
    }
}
