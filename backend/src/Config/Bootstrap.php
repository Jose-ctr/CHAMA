<?php

declare(strict_types=1);

namespace Chama\Config;

use Dotenv\Dotenv;

final class Bootstrap
{
    public static function load(): void
    {
        $backendPath = dirname(__DIR__, 2);

        $envFile = $backendPath . '/.env';

        if (file_exists($envFile)) {
            $dotenv = Dotenv::createImmutable($backendPath);
            $dotenv->safeLoad();
        }

        App::setTimezone();
    }

    private function __construct()
    {
    }
}
