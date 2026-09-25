<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * CSRF untuk SEMUA request state-changing (form & AJAX).
 * Token dikirim via: field _csrf  ATAU  header X-CSRF-Token.
 */
final class CsrfMiddleware
{
    public static function handle(Request $request): void
    {
        if (!$request->isStateChanging()) {
            return;
        }
        $token = $request->header('X-CSRF-Token');
        if ($token === null || $token === '') {
            $token = (string)$request->input('_csrf', '');
        }
        if (!Session::verifyCsrf($token !== '' ? $token : null)) {
            if ($request->expectsJson()) {
                Response::jsonError('Token CSRF tidak valid. Muat ulang halaman.', ['_csrf' => 'invalid'], 403, [
                    'code' => 'csrf_invalid',
                ]);
            }
            throw new HttpException(403, 'Token keamanan (CSRF) tidak valid. Muat ulang halaman dan coba lagi.');
        }
    }
}
