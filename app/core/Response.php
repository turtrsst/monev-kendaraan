<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function html(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        echo $body;
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function jsonOk(string $message = 'OK', array $data = [], int $status = 200): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function jsonError(string $message, array $errors = [], int $status = 400, array $extra = []): never
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors] + $extra, $status);
    }

    public static function redirect(string $to, int $status = 302): never
    {
        // Hanya izinkan path relatif atau URL absolut milik aplikasi (anti open redirect)
        if (!str_starts_with($to, '/') || str_starts_with($to, '//')) {
            $base = (string)config('app.url');
            if ($base !== '' && !str_starts_with($to, $base)) {
                $to = '/beranda';
            }
        }
        http_response_code($status);
        header('Location: ' . $to);
        exit;
    }

    public static function abort(int $status, string $message = '', array $errors = []): never
    {
        throw new HttpException($status, $message, $errors);
    }
}
