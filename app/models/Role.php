<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Role
{
    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return DB::fetchAll('SELECT * FROM roles WHERE is_active = 1 ORDER BY name');
    }

    public static function findBySlug(string $slug): ?array
    {
        return DB::fetch('SELECT * FROM roles WHERE slug = ? AND is_active = 1', [$slug]);
    }

    /** @return string[] permission list milik role */
    public static function permissions(string $slug): array
    {
        $role = self::findBySlug($slug);
        if ($role === null || empty($role['permissions'])) {
            return [];
        }
        $decoded = json_decode((string)$role['permissions'], true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
