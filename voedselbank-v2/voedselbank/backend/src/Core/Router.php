<?php
declare(strict_types=1);

namespace App\Core;

use App\Auth\Auth;
use Throwable;

/**
 * Koppelt een URL aan een controller-methode en bewaakt de rechten.
 *
 *   $router->get('/klanten/{id}/pakketten', [KlantController::class, 'pakketten'], 'klant_inzien');
 *
 * Het derde argument is wat er nodig is om de route te gebruiken:
 *   Router::PUBLIC   iedereen (alleen inloggen en "wie ben ik")
 *   Router::LOGIN    elke ingelogde gebruiker
 *   'klant_inzien'   een permissie-code uit de tabel Permissie
 *   ['a', 'b']       één van deze permissies is genoeg
 *
 * Omdat de controle hier zit, kan een controller hem niet vergeten
 * (US-SEC-01: knoppen verbergen in de frontend is niet genoeg).
 */
final class Router
{
    public const PUBLIC = 'public';
    public const LOGIN  = 'login';

    /** @var array<int,array{method:string,pattern:string,regex:string,handler:array,access:string|array}> */
    private array $routes = [];

    public function get(string $pad, array $handler, string|array $toegang = self::LOGIN): void    { $this->add('GET', $pad, $handler, $toegang); }
    public function post(string $pad, array $handler, string|array $toegang = self::LOGIN): void   { $this->add('POST', $pad, $handler, $toegang); }
    public function put(string $pad, array $handler, string|array $toegang = self::LOGIN): void    { $this->add('PUT', $pad, $handler, $toegang); }
    public function patch(string $pad, array $handler, string|array $toegang = self::LOGIN): void  { $this->add('PATCH', $pad, $handler, $toegang); }
    public function delete(string $pad, array $handler, string|array $toegang = self::LOGIN): void { $this->add('DELETE', $pad, $handler, $toegang); }

    private function add(string $methode, string $pad, array $handler, string|array $toegang): void
    {
        // /klanten/{id} -> #^/klanten/(?P<id>[^/]+)$#
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pad) . '$#';
        $this->routes[] = [
            'method'  => $methode,
            'pattern' => $pad,
            'regex'   => $regex,
            'handler' => $handler,
            'access'  => $toegang,
        ];
    }

    /** Alle routes, voor documentatie en tests. */
    public function routes(): array
    {
        return array_map(static fn(array $r): array => [
            'method' => $r['method'], 'pad' => $r['pattern'], 'toegang' => $r['access'],
        ], $this->routes);
    }

    public function dispatch(Request $request): Response
    {
        try {
            [$route, $params] = $this->match($request);
            $this->authorize($route['access']);

            [$klasse, $methode] = $route['handler'];
            $uitkomst = (new $klasse())->$methode($request->withParams($params));

            return $uitkomst instanceof Response ? $uitkomst : Response::json($uitkomst);
        } catch (HttpException $e) {
            return Response::json($e->body(), $e->status());
        } catch (Throwable $e) {
            // nooit een stacktrace naar de browser; wel in het serverlog
            error_log('[api] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return Response::json(['fout' => 'Er ging iets mis op de server.'], 500);
        }
    }

    /** @return array{0:array,1:array<string,string>} */
    private function match(Request $request): array
    {
        $padBestaat = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            $padBestaat = true;
            if ($route['method'] === $request->method) {
                return [$route, array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
        }

        if ($padBestaat) {
            throw new HttpException(405, 'Deze actie kan niet met ' . $request->method . '.');
        }
        throw new HttpException(404, 'Onbekende API-route: ' . $request->path);
    }

    private function authorize(string|array $toegang): void
    {
        if ($toegang === self::PUBLIC) {
            return;
        }
        if ($toegang === self::LOGIN) {
            Auth::requireLogin();
            return;
        }
        Auth::requirePermission(...(array) $toegang);
    }
}
