<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $path = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');
        if ($path !== '/') $path = rtrim($path, '/');
        foreach ($this->routes as $route) {
            if ($route['method'] === strtoupper($method) && $route['path'] === $path) return ($route['handler'])();
        }
        http_response_code(404);
        return null;
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/') $path = rtrim($path, '/');
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) throw new RuntimeException('A route is already registered for this method and path.');
        }
        $this->routes[] = ['method' => $method, 'path' => $path, 'handler' => $handler];
    }
}
