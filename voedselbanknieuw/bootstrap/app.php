<?php

use App\Exceptions\HttpException;
use App\Http\Middleware\Ingelogd;
use App\Http\Middleware\Permissie;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * De API werkt met een sessiecookie (net als de rest van de site), dus
         * zetten we de sessie-middleware ook op de API-routes. Zonder dit weet
         * /api/... niet wie er is ingelogd.
         */
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        /* Korte namen voor onze eigen middleware, zie routes/api.php. */
        $middleware->alias([
            'ingelogd' => Ingelogd::class,
            'mag'      => Permissie::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Alle fouten van de API worden hier omgezet naar JSON met een
         * Nederlandse melding. De browser krijgt nooit een stacktrace of een
         * bestandspad te zien (OWASP A05: security misconfiguration); de
         * technische details gaan naar storage/logs/laravel.log.
         */
        /*
         * Een 401, 403 of 422 is geen storing maar een normale afloop: de
         * gebruiker mag iets niet of vult iets verkeerd in. Die horen dus
         * niet in het foutenlogboek, anders zie je de echte fouten niet meer.
         */
        $exceptions->dontReport(HttpException::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (HttpException $e) {
            return response()->json($e->body(), $e->status());
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['fout' => 'Onbekende API-route: ' . $request->path()], 404);
            }
            return null;
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['fout' => 'Deze actie kan niet met ' . $request->method() . '.'], 405);
            }
            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && !$e instanceof HttpException) {
                return response()->json(['fout' => 'Er ging iets mis op de server.'], 500);
            }
            return null;
        });
    })->create();
