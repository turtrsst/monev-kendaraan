<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware
{
    public static function handle(Request $request): void
    {
        if (Session::user() === null) {
            if ($request->expectsJson()) {
                Response::jsonError('Belum masuk atau sesi berakhir.', [], 401, [
                    'code' => 'unauthenticated',
                ]);
            }
            // simpan tujuan untuk redirect setelah login (path relatif saja)
            $path = $request->path();
            if (str_starts_with($path, '/') && !str_starts_with($path, '//')) {
                Session::setIntended($path . ($request->fullUrl() !== $path && str_contains($request->fullUrl(), '?')
                    ? '?' . explode('?', $request->fullUrl(), 2)[1] : ''));
            }
            Response::redirect('/login');
        }
    }
}
