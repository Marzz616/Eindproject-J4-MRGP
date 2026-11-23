<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * TECH-03 & TECH-04: Configuratie van Web & API routing en Role-Based Access Control middleware
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // US-SEC-01 & US-GBR-05 t/m 09: Koppel de 'role' alias aan RoleMiddleware
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // TECH-06: Centrale foutafhandeling
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $exception, $request) {
            if ($request->is('api/*')) {
                return response()->json(['login_nodig' => true, 'error' => 'Log eerst in.'], 401);
            }
        });
    })->create();