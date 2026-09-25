<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

final class AuthController
{
    public function showLogin(Request $request): never
    {
        View::show('modules/auth/views/login.php', [
            'title' => 'Masuk',
            'errors' => [],
            'old' => [],
        ], 'app/views/layouts/guest');
    }

    public function login(Request $request): never
    {
        $username = trim((string)$request->input('username', ''));
        $password = (string)$request->input('password', '');

        try {
            $user = AuthService::attempt($username, $password, $request->ip(), $request->userAgent());

            // Driver → beranda driver (nanti berbeda); seluruh role → /beranda
            $intended = Session::pullIntended();
            $dest = is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')
                ? $intended
                : '/beranda';
            if (!empty($user['force_password_change'])) {
                $dest = '/ganti-password';
            }
            Response::redirect($dest);
        } catch (\App\Core\HttpException $e) {
            // Render ulang form (status asli agar log & throttle tetap tercatat)
            $status = $e->status;
            View::show('modules/auth/views/login.php', [
                'title' => 'Masuk',
                'errors' => $e->errors + ['_general' => $e->errors === [] ? $e->getMessage() : ''],
                'old' => ['username' => $username],
                'status' => $status,
            ], 'app/views/layouts/guest', in_array($status, [401, 403, 419, 422, 429], true) ? $status : 200);
        }
    }

    public function logout(Request $request): never
    {
        AuthService::logout();
        Response::redirect('/login');
    }

    public function showChangePassword(Request $request): never
    {
        View::show('modules/auth/views/change_password.php', [
            'title' => 'Ganti Password',
            'errors' => [],
        ]);
    }

    public function changePassword(Request $request): never
    {
        $user = Session::user();
        if ($user === null) {
            Response::redirect('/login');
        }
        try {
            AuthService::changePassword(
                (int)$user['id'],
                (string)$request->input('current_password', ''),
                (string)$request->input('new_password', ''),
                (string)$request->input('confirm_password', '')
            );
            Response::redirect('/beranda');
        } catch (\App\Core\HttpException $e) {
            View::show('modules/auth/views/change_password.php', [
                'title' => 'Ganti Password',
                'errors' => $e->errors + ['_general' => $e->errors === [] ? $e->getMessage() : ''],
                'old' => [
                    'username' => $request->input('username'),
                ],
                'status' => $e->status,
            ], 'app/views/layouts/app', $e->status === 422 ? 422 : 200);
        }
    }
}
