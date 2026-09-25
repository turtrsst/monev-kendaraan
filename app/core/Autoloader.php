<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autoloader sederhana berbasis prefix (PSR-4-like, direktori huruf kecil).
 *
 *   App\Core\X        → app/core/X.php
 *   App\Models\X      → app/models/X.php
 *   App\Services\X    → app/services/X.php
 *   App\Validators\X  → app/validators/X.php
 *   App\Middleware\X  → app/middleware/X.php
 *   Modules\Auth\Controllers\X → modules/auth/controllers/X.php
 *   Api\Auth\X        → api/auth/X.php
 */
final class Autoloader
{
    private const PREFIXES = [
        'App\\Core\\' => 'app/core/',
        'App\\Models\\' => 'app/models/',
        'App\\Services\\' => 'app/services/',
        'App\\Validators\\' => 'app/validators/',
        'App\\Middleware\\' => 'app/middleware/',
        'Modules\\' => 'modules/',
        'Api\\' => 'api/',
    ];

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        foreach (self::PREFIXES as $prefix => $dir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $parts = explode('\\', $relative);
            $classFile = array_pop($parts); // nama kelas tetap kapital: App.php, User.php, …
            $segments = array_map('strtolower', $parts);
            $segments[] = $classFile;
            $path = BASE_PATH . '/' . $dir . implode('/', $segments) . '.php';
            if (is_file($path)) {
                require $path;
                return;
            }
        }
    }
}
