<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class RoleMiddleware
{
    /** @param string $allowedSlugs daftar slug dipisah koma, mis. "admin,operator" */
    public static function handle(Request $request, string $allowedSlugs): void
    {
        $user = Session::user();
        if ($user === null) {
            // dilewati AuthMiddleware sebelumnya; fallback aman:
            if ($request->expectsJson()) {
                Response::jsonError('Belum masuk.', [], 401, ['code' => 'unauthenticated']);
            }
            Response::redirect('/login');
        }
        $allowed = array_filter(array_map('trim', explode(',', $allowedSlugs)));
        if (!in_array($user['role'], $allowed, true)) {
            if ($request->expectsJson()) {
                Response::jsonError('Anda tidak memiliki akses ke resource ini.', [], 403, ['code' => 'forbidden']);
            }
            throw new \App\Core\HttpException(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
    }
}
