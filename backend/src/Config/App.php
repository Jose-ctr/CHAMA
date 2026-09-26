<?php

declare(strict_types=1);

namespace Chama\Config;

final class App
{
    public static function name(): string
    {
        return getenv('APP_NAME') ?: 'CHAMA';
    }

    public static function environment(): string
    {
        return getenv('APP_ENV') ?: 'local';
    }

    public static function debug(): bool
    {
        return filter_var(
            getenv('APP_DEBUG') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function url(): string
    {
        return rtrim(
            getenv('APP_URL') ?: 'http://localhost:8000',
            '/'
        );
    }

    public static function apiVersion(): string
    {
        return getenv('API_VERSION') ?: 'v1';
    }

    public static function apiPrefix(): string
    {
        return rtrim(
            getenv('API_PREFIX') ?: '/api',
            '/'
        );
    }

    public static function timezone(): string
    {
        return getenv('APP_TIMEZONE') ?: 'Africa/Nairobi';
    }

    public static function sessionName(): string
    {
        return getenv('SESSION_NAME') ?: 'chama_session';
    }

    public static function sessionLifetime(): int
    {
        return (int) (getenv('SESSION_LIFETIME') ?: 86400);
    }

    public static function corsOrigins(): array
    {
        $origins = getenv('CORS_ALLOWED_ORIGINS') ?: '';

        if ($origins === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(',', $origins)
                )
            )
        );
    }

    public static function setTimezone(): void
    {
        date_default_timezone_set(self::timezone());
    }

    private function __construct()
    {
    }
}
