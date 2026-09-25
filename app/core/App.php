<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\SettingsService;

/**
 * Kernel aplikasi: boot → sesi → timeout → settings → routing → error handling.
 */
final class App
{
    public static ?Request $request = null;

    public static function run(): void
    {
        // Front controller CLI-server (php -S): biarkan aset statis dilayani server
        if (PHP_SAPI === 'cli-server') {
            $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (is_string($p) && $p !== '/' && $p !== '') {
                $file = __DIR__ . '/../../public' . $p;
                if (is_file($file) && !str_ends_with($p, '.php')) {
                    return; // return false handled di index.php
                }
            }
        }

        $config = config();
        date_default_timezone_set($config['app']['timezone']);

        // Security headers global (sebelum output apa pun)
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: same-origin');
            header('Permissions-Policy: camera=(self), geolocation=(self)');
        }

        self::registerErrorHandling();

        Session::start();

        // Idle & absolute timeout — tanpa memperpanjang sesi
        $sessionValid = Session::checkTimeouts();

        // Settings dari DB (fallback: kosong bila DB belum siap)
        try {
            SettingsService::load();
        } catch (\Throwable $e) {
            logger('warning', 'Settings tidak dapat dimuat: ' . $e->getMessage());
        }

        self::$request = Request::capture();
        $request = self::$request;

        if (!$sessionValid && $request->expectsJson()) {
            Response::jsonError('Sesi berakhir. Silakan masuk kembali.', [], 401, ['code' => 'session_expired']);
        }

        $router = new Router();
        $routes = require BASE_PATH . '/app/config/routes.php';
        $routes($router);

        try {
            $router->dispatch($request);
        } catch (HttpException $e) {
            self::renderError($request, $e->status, $e->getMessage(), $e->errors, $e->extra);
        } catch (\Throwable $e) {
            logger('error', 'Uncaught: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 1500),
            ]);
            $debug = (bool)config('app.debug');
            self::renderError(
                $request,
                500,
                $debug ? $e->getMessage() : 'Terjadi kesalahan pada server.',
                [],
                $debug ? ['exception' => get_class($e), 'at' => $e->getFile() . ':' . $e->getLine()] : []
            );
        }
        // tidak boleh sampai sini
        exit;
    }

    private static function renderError(Request $request, int $status, string $message, array $errors = [], array $extra = []): never
    {
        if ($request->expectsJson()) {
            Response::json(['success' => false, 'message' => $message, 'errors' => $errors] + $extra, $status);
        }
        $template = 'app/views/errors/' . $status . '.php';
        if (!is_file(BASE_PATH . '/' . $template)) {
            $template = 'app/views/errors/generic.php';
        }
        $body = View::partial($template, [
            'status' => $status,
            'message' => $message,
        ]);
        Response::html($body, $status);
    }

    private static function registerErrorHandling(): void
    {
        ini_set('display_errors', config('app.debug') ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', config('paths.logs') . '/php-error.log');

        set_exception_handler(static function (\Throwable $e) {
            logger('error', 'Exception: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            $status = $e instanceof HttpException ? $e->status : 500;
            $req = self::$request;
            if ($req !== null) {
                try {
                    self::renderError(
                        $req,
                        $status,
                        (string)$e->getMessage(),
                        $e instanceof HttpException ? $e->errors : []
                    );
                } catch (\Throwable) {
                    http_response_code($status);
                    echo 'Terjadi kesalahan.';
                    exit;
                }
            }
            http_response_code($status);
            echo 'Terjadi kesalahan.';
            exit;
        });
    }
}
