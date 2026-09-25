<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesi aman: cookie HttpOnly/SameSite/[Secure], strict mode,
 * regenerasi ID saat login, idle & absolute timeout server-side.
 *
 * Penting (sesuai approval Phase 0):
 *  - Timeout tidak diperpanjang oleh request biasa.
 *  - Hanya heartbeat /api/auth/heartbeat (dikirim client saat ADA aktivitas user)
 *    yang memperbarui last_activity → tidak ada keep-alive tanpa aktivitas.
 */
final class Session
{
    private const KEY_TOKEN = '_csrf';
    private const KEY_LAST = '_last_activity';
    private const KEY_LOGIN_AT = '_login_at';
    private const KEY_USER = '_auth_user';
    private const KEY_FLASH = '_flash';
    private const KEY_INTENDED = '_intended';

    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        $cfg = config('session');

        if (PHP_SAPI !== 'cli') {
            session_name($cfg['name']);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $cfg['cookie_secure'],
                'httponly' => true,
                'samesite' => $cfg['samesite'],
            ]);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_start();
        self::$started = true;
    }

    /** Cek timeout TANPA memperpanjang sesi. true = masih berlaku. */
    public static function checkTimeouts(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $cfg = config('session');
        $now = time();
        $last = (int)($_SESSION[self::KEY_LAST] ?? 0);
        $loginAt = (int)($_SESSION[self::KEY_LOGIN_AT] ?? 0);

        if ($last <= 0 || $loginAt <= 0) {
            return true; // belum login (halaman publik)
        }
        $idleExpired = ($now - $last) > (int)$cfg['idle_seconds'];
        $absoluteExpired = ($now - $loginAt) > (int)$cfg['absolute_seconds'];
        if ($idleExpired || $absoluteExpired) {
            self::destroy();
            return false;
        }
        return true;
    }

    /** Dipanggil HANYA oleh heartbeat ketika user benar-benar aktif. */
    public static function markActivity(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::KEY_LAST] = time();
        }
    }

    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true); // anti session fixation
        $_SESSION[self::KEY_USER] = [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'name' => (string)$user['name'],
            'role' => (string)$user['role_slug'],
            'role_name' => (string)$user['role_name'],
            'force_password_change' => (bool)$user['force_password_change'],
        ];
        $_SESSION[self::KEY_LOGIN_AT] = time();
        $_SESSION[self::KEY_LAST] = time();
        $_SESSION[self::KEY_TOKEN] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function destroy(): void
    {
        self::logout();
    }

    public static function user(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        return $_SESSION[self::KEY_USER] ?? null;
    }

    public static function userId(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function isFreshLogin(): bool
    {
        return self::user() !== null;
    }

    /** Sisa detik idle sampai expired (untuk heartbeat response). */
    public static function idleSecondsRemaining(): int
    {
        $cfg = config('session');
        $last = (int)($_SESSION[self::KEY_LAST] ?? 0);
        if ($last <= 0) {
            return 0;
        }
        return max(0, (int)$cfg['idle_seconds'] - (time() - $last));
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION[self::KEY_TOKEN]) || !is_string($_SESSION[self::KEY_TOKEN])) {
            $_SESSION[self::KEY_TOKEN] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY_TOKEN];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        $known = $_SESSION[self::KEY_TOKEN] ?? '';
        return is_string($token) && $token !== '' && hash_equals((string)$known, $token);
    }

    /** Regenerasi token CSRF (mis. setelah login) — lama ditolak. */
    public static function rotateCsrf(): void
    {
        self::start();
        $_SESSION[self::KEY_TOKEN] = bin2hex(random_bytes(32));
    }

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION[self::KEY_FLASH][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function pullFlash(): array
    {
        self::start();
        $f = $_SESSION[self::KEY_FLASH] ?? [];
        unset($_SESSION[self::KEY_FLASH]);
        return $f;
    }

    public static function setIntended(string $url): void
    {
        self::start();
        $_SESSION[self::KEY_INTENDED] = $url;
    }

    public static function pullIntended(): ?string
    {
        self::start();
        $u = $_SESSION[self::KEY_INTENDED] ?? null;
        unset($_SESSION[self::KEY_INTENDED]);
        return $u;
    }
}
