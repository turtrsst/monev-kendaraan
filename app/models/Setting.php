<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Setting
{
    /** @return array<string,array<string,mixed>> map skey → row */
    public static function allMap(): array
    {
        $map = [];
        foreach (DB::fetchAll('SELECT skey, value, description, updated_at FROM settings ORDER BY skey') as $row) {
            $map[(string)$row['skey']] = $row;
        }
        return $map;
    }

    public static function put(string $skey, ?string $value, ?int $updatedBy = null): void
    {
        DB::run(
            'INSERT INTO settings (skey, value, updated_by) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
            [$skey, $value, $updatedBy]
        );
    }
}
