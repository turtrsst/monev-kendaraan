<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware;

/**
 * Router + pipeline middleware.
 * Middleware tersedia: auth, guest, csrf, force_password_change,
 * role:admin,operator  ·  ratelimit:60 (per menit)
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,params:array<string>,handler:mixed,middleware:array<int,string>}> */
    private array $routes = [];

    public function get(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, mixed $handler, array $middleware): void
    {
        $params = [];
        $regex = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', static function ($m) use (&$params) {
            $params[$m[1]] = true;
            return '([A-Za-z0-9_\-]+)';
        }, $pattern);
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): never
    {
        $path = $request->path();
        $method = $request->method();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $args = [];
            $i = 1;
            foreach (array_keys($route['params']) as $name) {
                $args[$name] = $m[$i++] ?? null;
            }
            $this->runMiddleware($route['middleware'], $request);
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $controller = new $class();
                $controller->$action($request, ...array_values($args));
            } else {
                $handler($request, ...array_values($args));
            }
            // handler diwajibkan mengakhiri melalui Response::* (exit)
            throw new HttpException(500, 'Handler tidak mengirim respons.');
        }
        throw new HttpException(404);
    }

    /** @param array<int,string> $middlewareList */
    private function runMiddleware(array $middlewareList, Request $request): void
    {
        foreach ($middlewareList as $spec) {
            [$name, $arg] = array_pad(explode(':', $spec, 2), 2, null);
            switch ($name) {
                case 'auth':
                    Middleware\AuthMiddleware::handle($request);
                    break;
                case 'guest':
                    Middleware\GuestMiddleware::handle($request);
                    break;
                case 'csrf':
                    Middleware\CsrfMiddleware::handle($request);
                    break;
                case 'force_password_change':
                    Middleware\ForcePasswordChangeMiddleware::handle($request);
                    break;
                case 'role':
                    Middleware\RoleMiddleware::handle($request, (string)$arg);
                    break;
                case 'ratelimit':
                    Middleware\RateLimitMiddleware::handle($request, (int)($arg ?: 120));
                    break;
                default:
                    throw new \RuntimeException('Middleware tidak dikenal: ' . $name);
            }
        }
    }
}
