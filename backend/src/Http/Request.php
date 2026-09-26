<?php

declare(strict_types=1);

namespace Chama\Http;

final class Request
{
    private array $body;

    public function __construct()
    {
        $this->body = $this->parseBody();
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $path = parse_url($uri, PHP_URL_PATH);

        return $path !== false && $path !== null
            ? $path
            : '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function query(
        string $key,
        mixed $default = null
    ): mixed {
        return $_GET[$key] ?? $default;
    }

    public function queryAll(): array
    {
        return $_GET;
    }

    public function header(
        string $name,
        mixed $default = null
    ): mixed {
        $serverKey = 'HTTP_' . strtoupper(
            str_replace('-', '_', $name)
        );

        return $_SERVER[$serverKey] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->header('Authorization');

        if (!is_string($authorization)) {
            return null;
        }

        if (!preg_match(
            '/^Bearer\s+(.+)$/i',
            $authorization,
            $matches
        )) {
            return null;
        }

        return trim($matches[1]);
    }

    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (
            stripos(
                $contentType,
                'application/json'
            ) !== false
        ) {
            $raw = file_get_contents('php://input');

            if ($raw === false || trim($raw) === '') {
                return [];
            }

            $decoded = json_decode(
                $raw,
                true
            );

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return $_POST;
    }
}
