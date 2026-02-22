<?php

namespace ShopCode\Core;

use ShopCode\Middleware\AuthMiddleware;
use ShopCode\Middleware\RoleMiddleware;
use ShopCode\Middleware\ApprovedMiddleware;
use ShopCode\Middleware\ModuleMiddleware;

class Router
{
    private array $routes;
    private Request $request;

    public function __construct(array $routes, Request $request)
    {
        $this->routes  = $routes;
        $this->request = $request;
    }

    public function dispatch(): void
    {
        foreach ($this->routes as $route) {
            [$method, $path, $handler, $middleware] = $route;

            if ($this->request->method !== strtoupper($method)) {
                continue;
            }

            $params = $this->matchPath($path, $this->request->path);
            if ($params === false) {
                continue;
            }

            // Předáme route parametry do requestu
            $this->request->params = $params;

            // Spustíme middleware
            $this->runMiddleware($middleware);

            // Zavoláme controller
            $this->callHandler($handler);
            return;
        }

        // 404
        Response::notFound();
    }

    private function matchPath(string $routePath, string $requestPath): array|false
    {
        // Konvertujeme {param} na regex skupiny
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return false;
        }

        // Vrátíme jen pojmenované skupiny
        return array_filter($matches, fn($k) => !is_numeric($k), ARRAY_FILTER_USE_KEY);
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $mw) {
            match (true) {
                $mw === 'auth'        => AuthMiddleware::handle($this->request),
                $mw === 'approved'    => ApprovedMiddleware::handle($this->request),
                $mw === 'superadmin'  => RoleMiddleware::handle($this->request, 'superadmin'),
                str_starts_with($mw, 'module:') => ModuleMiddleware::handle(
                    $this->request,
                    substr($mw, 7)
                ),
                default => null,
            };
        }
    }

    private function callHandler(string $handler): void
    {
        [$class, $method] = explode('@', $handler);

        // Namespace prefix
        $namespace = str_contains($class, '\\')
            ? "ShopCode\\Controllers\\{$class}"
            : "ShopCode\\Controllers\\{$class}";

        if (!class_exists($namespace)) {
            die("Controller nenalezen: {$namespace}");
        }

        $controller = new $namespace($this->request);
        if (!method_exists($controller, $method)) {
            die("Metoda nenalezena: {$namespace}::{$method}");
        }

        $controller->$method();
    }
}
