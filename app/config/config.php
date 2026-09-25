<?php

declare(strict_types=1);

/**
 * Konfigurasi terpusat — semua nilai dari .env / environment.
 * Jangan hard-code password, radius GPS, ukuran upload, timezone, atau URL di tempat lain.
 */

use App\Core\Env;

Env::load(BASE_PATH . '/.env');

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Fleet Logbook & Monitoring'),
        'env' => Env::get('APP_ENV', 'production'),
        'url' => rtrim((string)Env::get('APP_URL', ''), '/'),
        'debug' => Env::bool('APP_DEBUG', false),
        'timezone' => Env::get('APP_TIMEZONE', 'Asia/Jakarta'),
    ],

    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => (int)Env::get('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', 'kendaraan_logbook'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => (string)Env::get('DB_PASS', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    'session' => [
        'name' => Env::get('SESSION_NAME', 'fleet_sid'),
        'idle_seconds' => ((int)Env::get('SESSION_IDLE_TIMEOUT', 30)) * 60,
        'absolute_seconds' => ((int)Env::get('SESSION_ABSOLUTE_TIMEOUT', 720)) * 60,
        'warning_seconds' => 120, // peringatan sebelum expired (client heartbeat)
        'cookie_secure' => Env::bool('SESSION_COOKIE_SECURE', false), // true di produksi HTTPS
        'samesite' => Env::get('SESSION_SAMESITE', 'Lax'),
    ],

    'security' => [
        'login_max_attempts' => (int)Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'login_lockout_minutes' => (int)Env::get('LOGIN_LOCKOUT_MINUTES', 15),
        'rate_limit_default_per_minute' => (int)Env::get('RATE_LIMIT_DEFAULT', 120),
    ],

    'upload' => [
        'max_mb' => (int)Env::get('MAX_UPLOAD_MB', 5),
    ],

    'paths' => [
        'base' => BASE_PATH,
        'storage' => BASE_PATH . '/storage',
        'logs' => BASE_PATH . '/storage/logs',
        'cache' => BASE_PATH . '/storage/cache',
        'private' => BASE_PATH . '/storage/private',
        'uploads_tmp' => BASE_PATH . '/storage/temp',
    ],
];
