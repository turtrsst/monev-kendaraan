<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loader .env sederhana — tanpa library eksternal.
 * Aturan: BARIS= nilai, # komentar, kutip ""/'', env OS menang atas file.
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): array
    {
        if (self::$loaded) {
            return $_ENV;
        }
        self::$loaded = true;
        if (!is_file($path)) {
            return $_ENV;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if ($key === '' || getenv($key) !== false) {
                continue; // env OS menang
            }
            if ((strlen($val) >= 2 && $val[0] === '"' && str_ends_with($val, '"'))
                || (strlen($val) >= 2 && $val[0] === "'" && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            $_ENV[$key] = $val;
            putenv($key . '=' . $val);
        }
        return $_ENV;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = $_ENV[$key] ?? null;
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
    }
}
