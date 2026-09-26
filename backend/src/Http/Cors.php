<?php

declare(strict_types=1);

namespace Chama\Http;

use Chama\Config\App;

final class Cors
{
    public static function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $allowedOrigins = App::corsOrigins();

        if (
            $origin !== '' &&
            in_array($origin, $allowedOrigins, true)
        ) {
            header(
                'Access-Control-Allow-Origin: ' . $origin
            );

            header(
                'Access-Control-Allow-Credentials: true'
            );

            header(
                'Vary: Origin'
            );
        }

        header(
            'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS'
        );

        header(
            'Access-Control-Allow-Headers: Content-Type, Authorization, Accept'
        );

        header(
            'Access-Control-Max-Age: 86400'
        );

        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS'
        ) {
            http_response_code(204);
            exit;
        }
    }

    private function __construct()
    {
    }
}
