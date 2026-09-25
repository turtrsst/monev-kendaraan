<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Paksa ganti password pertama kali (force_password_change = 1).
 * Halaman whitelist: ganti-password, logout, dan API sesi (me/heartbeat/logout).
 */
final class ForcePasswordChangeMiddleware
{
    private const WHITELIST = [
        '/ganti-password',
        '/logout',
        '/api/auth/me',
        '/api/auth/heartbeat',
        '/api/auth/logout',
        '/assets',
    ];

    public static function handle(Request $request): void
    {
        $user = Session::user();
        if ($user === null || empty($user['force_password_change'])) {
            return;
        }
        $path = $request->path();
        foreach (self::WHITELIST as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return;
            }
        }
        if ($request->expectsJson()) {
            Response::jsonError('Anda wajib mengubah password terlebih dahulu.', [], 403, [
                'code' => 'force_password_change',
            ]);
        }
        // Kontrak: gate = 403 (bukan redirect) — pesan di halaman 403
        throw new \App\Core\HttpException(403, 'Anda wajib mengubah password terlebih dahulu. Buka menu Ganti Password.');
    }
}
