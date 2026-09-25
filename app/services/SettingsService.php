<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Cache settings per-request. Sumber kebenaran = tabel settings
 * (radius GPS, upload, timeout, logo, nama RS — tidak ada hard-code).
 */
final class SettingsService
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        $map = [];
        try {
            foreach (Setting::allMap() as $key => $row) {
                $map[$key] = (string)($row['value'] ?? '');
            }
        } catch (\Throwable $e) {
            logger('warning', 'Settings load gagal: ' . $e->getMessage());
            $map = [];
        }
        self::$cache = $map;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return is_numeric($v) ? (int)$v : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        Setting::put($key, $value, $userId);
        self::$cache = null; // bust cache
        self::load();
    }

    /** Test/gate: reset cache antar pengujian. */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
