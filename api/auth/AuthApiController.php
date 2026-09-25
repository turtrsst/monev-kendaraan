<?php

declare(strict_types=1);

namespace Api\Auth;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

/**
 * API sesi untuk SPA/AJAX/future mobile app.
 * Respons konsisten: {success, message, data|errors}.
 */
final class AuthApiController
{
    public function me(Request $request): never
    {
        $user = Session::user();
        if ($user === null) {
            Response::jsonError('Belum masuk.', [], 401, ['code' => 'unauthenticated']);
        }
        Response::jsonOk('OK', [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role'],
                'role_name' => $user['role_name'],
                'force_password_change' => (bool)$user['force_password_change'],
            ],
            'idle_remaining' => Session::idleSecondsRemaining(),
        ]);
    }

    /**
     * Keep-alive HANYA ketika ada aktivitas user (dikirim oleh app.js
     * setelah event pointer/keyboard/scroll). Tidak ada polling otomatis.
     */
    public function heartbeat(Request $request): never
    {
        if (Session::user() === null) {
            Response::jsonError('Sesi berakhir.', [], 401, ['code' => 'session_expired']);
        }
        Session::markActivity();
        $remaining = Session::idleSecondsRemaining();
        $warningAt = (int)config('session.warning_seconds');
        Response::jsonOk('OK', [
            'idle_remaining' => $remaining,
            'warn' => $remaining <= $warningAt,
            'warning_seconds' => $warningAt,
        ]);
    }

    public function logout(Request $request): never
    {
        if (Session::user() !== null) {
            AuthService::logout();
        }
        Response::jsonOk('Sesi berakhir.');
    }
}
