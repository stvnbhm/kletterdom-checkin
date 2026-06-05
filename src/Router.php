<?php

declare(strict_types=1);

namespace Kletterdom;

use Closure;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;

/**
 * Tabellen-basierter Router. Routen werden als Array deklariert und
 * können Path-Parameter (`{token}`) sowie eine Middleware-Liste
 * tragen. Middleware kann jederzeit eine Response zurückgeben und
 * damit den Controller überspringen.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, params:array<int,string>, handler:callable, middleware:array<int,string>}> */
    private array $routes = [];

    /**
     * @param array<int,string> $middleware Middleware-Klassen (FQCN)
     * @param callable          $handler    fn(Container, Request, array<string,string>): Response
     */
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $params = [];
        $regex  = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '([^/]+)';
            },
            $pattern,
        );

        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'regex'      => '#^' . $regex . '$#',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Container $container, Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if (! preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }

            $vars = [];
            foreach ($route['params'] as $i => $name) {
                $vars[$name] = $matches[$i + 1] ?? '';
            }

            foreach ($route['middleware'] as $middlewareClass) {
                $middleware = $container->get($middlewareClass);
                $response   = $middleware->handle($request, $vars);
                if ($response instanceof Response) {
                    return $response;
                }
            }

            $handler = $route['handler'];
            if ($handler instanceof Closure || is_callable($handler)) {
                return $handler($container, $request, $vars);
            }
        }

        return Response::html('<h1>404 — Seite nicht gefunden</h1>', 404);
    }
}
