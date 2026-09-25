<?php

declare(strict_types=1);

/**
 * Application logger → storage/logs/app-YYYY-MM-DD.log (JSON lines).
 * Tidak pernah menampilkan SQL error/stack trace ke user — hanya ke log.
 */

function logger(string $level, string $message, array $context = []): void
{
    try {
        $dir = defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $file = $dir . '/app-' . date('Y-m-d') . '.log';
        $line = json_encode([
            'ts' => date('c'),
            'level' => $level,
            'msg' => $message,
            'ctx' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'user_id' => $_SESSION['_auth_user']['id'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    } catch (\Throwable) {
        // logging tidak boleh merusak aplikasi
    }
}
