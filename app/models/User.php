<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class User
{
    public static function findByUsername(string $username): ?array
    {
        return DB::fetch(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.username = ? AND u.deleted_at IS NULL',
            [$username]
        );
    }

    public static function find(int $id): ?array
    {
        return DB::fetch(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
    }

    public static function countAll(): int
    {
        return (int)DB::scalar('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
    }

    public static function markLoginSuccess(int $id): void
    {
        DB::run(
            'UPDATE users SET last_login_at = NOW(), failed_login_count = 0, locked_until = NULL WHERE id = ?',
            [$id]
        );
    }

    public static function markLoginFailure(int $id, int $maxAttempts, int $lockoutMinutes): void
    {
        DB::run(
            'UPDATE users
                SET failed_login_count = failed_login_count + 1,
                    locked_until = CASE WHEN failed_login_count + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE) ELSE locked_until END
              WHERE id = ?',
            [$maxAttempts, $lockoutMinutes, $id]
        );
    }

    public static function updatePassword(int $id, string $hash, bool $clearForce = true): void
    {
        DB::run(
            'UPDATE users SET password_hash = ?' . ($clearForce ? ', force_password_change = 0' : '') . ' WHERE id = ?',
            [$hash, $id]
        );
    }

    public static function clearLock(int $id): void
    {
        DB::run('UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = ?', [$id]);
    }
}
