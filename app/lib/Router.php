<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    /** $pattern: 'panel/oge/{id}' — {ad} bir yol parçasıyla eşleşir, {id} yalnızca rakam */
    public function add(string $methods, string $pattern, callable $handler): void
    {
        $re = preg_replace_callback('/\{(\w+)\}/', static fn($m) => $m[1] === 'id'
            ? '(?P<id>\d{1,10})'
            : '(?P<' . $m[1] . '>[^/]+)', $pattern);
        $this->routes[] = [explode('|', $methods), '#^' . $re . '$#u', $handler];
    }

    public function get(string $p, callable $h): void
    {
        $this->add('GET|HEAD', $p, $h);
    }

    public function post(string $p, callable $h): void
    {
        $this->add('POST', $p, $h);
    }

    public function any(string $p, callable $h): void
    {
        $this->add('GET|HEAD|POST', $p, $h);
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = false;
        foreach ($this->routes as [$methods, $re, $handler]) {
            if (!preg_match($re, $path, $m)) {
                continue;
            }
            if (!in_array($method, $methods, true)) {
                $allowed = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($method === 'POST') {
                Csrf::check();
            }
            $handler(...$params);
            return;
        }
        throw new HttpError($allowed ? 405 : 404);
    }
}
