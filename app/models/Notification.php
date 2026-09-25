<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Notification
{
    public static function create(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): int
    {
        return DB::insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'title' => mb_substr($title, 0, 150),
            'body' => $body !== null ? mb_substr($body, 0, 500) : null,
            'link' => $link,
        ]);
    }

    public static function unreadCount(int $userId): int
    {
        return (int)DB::scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function latest(int $userId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        return DB::fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT $limit",
            [$userId]
        );
    }
}
