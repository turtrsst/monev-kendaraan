<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class AuditLog
{
    public static function record(
        ?int $userId,
        string $action,
        ?string $entity = null,
        ?string $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): int {
        return DB::insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'old_data' => $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            'new_data' => $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return DB::fetchAll(
            "SELECT a.*, u.username
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
              ORDER BY a.id DESC
              LIMIT $limit"
        );
    }
}
