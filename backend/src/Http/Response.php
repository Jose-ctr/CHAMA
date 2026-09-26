<?php

declare(strict_types=1);

namespace Chama\Http;

final class Response
{
    public static function json(
        mixed $data = null,
        int $status = 200,
        array $headers = []
    ): never {
        http_response_code($status);

        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );

        exit;
    }

    public static function success(
        mixed $data = null,
        int $status = 200
    ): never {
        self::json(
            [
                'success' => true,
                'data' => $data,
            ],
            $status
        );
    }

    public static function error(
        string $message,
        int $status = 400,
        array $errors = []
    ): never {
        self::json(
            [
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ],
            $status
        );
    }

    private function __construct()
    {
    }
}
