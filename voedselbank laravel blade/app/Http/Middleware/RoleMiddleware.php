<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware voor Rollen & Rechten (US-GBR-05 t/m US-GBR-09, US-SEC-01, TECH-03, TECH-10)
 * - US-GBR-05: Directielid heeft toegang tot alle pagina's en functionaliteiten.
 * - US-GBR-06: Magazijnmedewerker heeft alleen toegang tot producten en leveranciers.
 * - US-GBR-07: Vrijwilliger heeft alleen toegang tot het samenstellen en uitdelen van pakketten.
 * - US-GBR-09: Vrijwilliger mag geen productbeheer uitvoeren.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        // US-GBR-03 & TECH-02: Weiger niet-ingelogde of geblokkeerde gebruikers
        if (!$user || $user->is_geblokkeerd) {
            return response()->json([
                'error' => 'Toegang geweigerd. Uw account is niet ingelogd of geblokkeerd (US-GBR-03).'
            ], 403);
        }

        // US-GBR-05: Directielid heeft altijd volledige toegang tot de applicatie
        if ($user->rol === 'directie') {
            return $next($request);
        }

        // US-SEC-01: Controleer of de huidige gebruikersrol toegang heeft tot deze route
        if (!in_array($user->rol, $roles, true)) {
            return response()->json([
                'error' => 'Toegang geweigerd: onvoldoende rechten voor deze functie (US-SEC-01).'
            ], 403);
        }

        return $next($request);
    }
}