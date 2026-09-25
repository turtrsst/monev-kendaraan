<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Session;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Validators\AuthValidator;

/**
 * Login, logout, throttle, ganti password.
 * - password_hash()/password_verify() — tidak pernah plaintext.
 * - Throttle: N gagal / M menit per (username, IP) → pesan generik.
 * - Pesan gagal login generik (tidak membocorkan keberadaan akun).
 */
final class AuthService
{
    /** @return array<string,mixed> user session payload */
    public static function attempt(string $username, string $password, string $ip, string $userAgent): array
    {
        $maxAttempts = (int)setting('auth.login_max_attempts', config('security.login_max_attempts'));
        $lockoutMinutes = (int)setting('auth.login_lockout_minutes', config('security.login_lockout_minutes'));

        $errors = AuthValidator::login($username, $password);
        if ($errors !== []) {
            throw new HttpException(422, 'Data login tidak valid.', $errors);
        }

        // Throttle berbasis tabel login_attempts (username + IP)
        $failures = LoginAttempt::recentFailures($username, $ip, $lockoutMinutes);
        if ($failures >= $maxAttempts) {
            AuditService::log('LOGIN_THROTTLED', 'user', $username, null, ['failures' => $failures]);
            LoginAttempt::record($username, $ip, false);
            throw new HttpException(429, "Terlalu banyak percobaan login. Coba lagi dalam {$lockoutMinutes} menit.", [
                'username' => 'Akun terkunci sementara karena terlalu banyak percobaan gagal.',
            ]);
        }

        $user = User::findByUsername($username);

        $genericFail = ['username' => 'Username atau password salah.'];

        if ($user === null) {
            // timing: hash dummy agar waktu respons seragam
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            LoginAttempt::record($username, $ip, false);
            AuditService::log('LOGIN_FAILED', 'user', $username);
            throw new HttpException(401, 'Username atau password salah.', $genericFail);
        }

        if (!empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time()) {
            AuditService::log('LOGIN_BLOCKED_LOCKED', 'user', $username);
            throw new HttpException(429, 'Akun terkunci sementara.', [
                'username' => 'Terlalu banyak percobaan gagal. Coba lagi nanti.',
            ]);
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            LoginAttempt::record($username, $ip, false);
            User::markLoginFailure((int)$user['id'], $maxAttempts, $lockoutMinutes);
            AuditService::log('LOGIN_FAILED', 'user', $username);
            throw new HttpException(401, 'Username atau password salah.', $genericFail);
        }

        if (empty($user['is_active'])) {
            LoginAttempt::record($username, $ip, false);
            AuditService::log('LOGIN_INACTIVE', 'user', $username);
            throw new HttpException(403, 'Akun tidak aktif.', [
                'username' => 'Akun Anda tidak aktif. Hubungi administrator.',
            ]);
        }

        // Sukses
        LoginAttempt::record($username, $ip, true);
        User::markLoginSuccess((int)$user['id']);
        AuditService::log('LOGIN_SUCCESS', 'user', $username);

        $payload = [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'name' => (string)$user['name'],
            'role_slug' => (string)$user['role_slug'],
            'role_name' => (string)$user['role_name'],
            'force_password_change' => !empty($user['force_password_change']),
        ];
        Session::login($payload);
        Session::rotateCsrf(); // token lama (pre-login) tidak berlaku lagi

        AuditService::log('LOGIN', 'user', (string)$user['id'], null, [
            'username' => $user['username'],
            'role' => $user['role_slug'],
        ]);

        return $payload;
    }

    public static function logout(): void
    {
        $user = Session::user();
        AuditService::log('LOGOUT', 'user', $user['id'] ?? null);
        Session::logout();
    }

    /** @param array<string,string> $errors */
    public static function changePassword(int $userId, string $current, string $new, string $confirm): void
    {
        $user = User::find($userId);
        if ($user === null) {
            throw new HttpException(404, 'Pengguna tidak ditemukan.');
        }
        $errors = AuthValidator::changePassword($current, $new, $confirm, (string)$user['password_hash']);
        if ($errors !== []) {
            throw new HttpException(422, 'Validasi gagal.', $errors);
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        User::updatePassword($userId, $hash, true);
        Session::rotateCsrf();
        // perbarui flag di sesi berjalan
        $sessUser = Session::user();
        if ($sessUser !== null) {
            $sessUser['force_password_change'] = false;
            // sederhana: set langsung lewat session superglobal
            $_SESSION['_auth_user'] = $sessUser;
        }
        AuditService::log('PASSWORD_CHANGE', 'user', (string)$userId);
        flash('success', 'Password berhasil diubah.');
    }

    public static function currentUser(): ?array
    {
        $sess = Session::user();
        if ($sess === null) {
            return null;
        }
        return User::find((int)$sess['id']);
    }
}
