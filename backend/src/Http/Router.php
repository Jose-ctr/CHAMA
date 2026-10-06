<?php

declare(strict_types=1);

namespace Chama\Http;

use RuntimeException;

final class Router
{
    private array $routes = [];

    public function get(
        string $path,
        callable $handler
    ): void {
        $this->add('GET', $path, $handler);
    }

    public function post(
        string $path,
        callable $handler
    ): void {
        $this->add('POST', $path, $handler);
    }

    public function put(
        string $path,
        callable $handler
    ): void {
        $this->add('PUT', $path, $handler);
    }

    public function patch(
        string $path,
        callable $handler
    ): void {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(
        string $path,
        callable $handler
    ): void {
        $this->add('DELETE', $path, $handler);
    }

    public function dispatch(
        Request $request
    ): mixed {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if (
                $route['method'] === $method
                && $route['path'] === $path
            ) {
                return ($route['handler'])($request);
            }
        }

        Response::error(
            'Route not found.',
            404
        );
    }

    private function add(
        string $method,
        string $path,
        callable $handler
    ): void {
        if ($path === '') {
            throw new RuntimeException(
                'Route path cannot be empty.'
            );
        }

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
        ];
    }
}

