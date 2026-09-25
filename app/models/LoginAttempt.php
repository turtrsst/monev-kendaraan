<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class LoginAttempt
{
    public static function record(string $username, string $ip, bool $success): void
    {
        DB::insert('login_attempts', [
            'username' => mb_substr($username, 0, 64),
            'ip' => mb_substr($ip, 0, 45),
            'success' => $success ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Jumlah kegagalan untuk (username, ip) dalam N menit terakhir. */
    public static function recentFailures(string $username, string $ip, int $minutes): int
    {
        return (int)DB::scalar(
            'SELECT COUNT(*) FROM login_attempts
              WHERE username = ? AND ip = ? AND success = 0
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [mb_substr($username, 0, 64), mb_substr($ip, 0, 45), $minutes]
        );
    }

    /** Bersihkan attempt lama (housekeeping ringan). */
    public static function purgeOlderThan(int $days = 30): void
    {
        DB::run('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
    }
}
