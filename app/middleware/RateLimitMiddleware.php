<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Rate limiter berbasis file (storage/cache) — tanpa Redis.
 * Key: IP + route path. Jendela: per menit.
 */
final class RateLimitMiddleware
{
    public static function handle(Request $request, int $maxPerMinute): void
    {
        $dir = config('paths.cache') . '/ratelimit';
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return; // gagal bikin cache → jangan blokir aplikasi
        }
        $key = sha1($request->method() . '|' . $request->ip() . '|' . $request->path());
        $file = $dir . '/' . $key . '.json';
        $bucket = (int)floor(time() / 60);

        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return;
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp) ?: '{}';
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['bucket'] ?? -1) !== $bucket) {
            $data = ['bucket' => $bucket, 'count' => 0];
        }
        $data['count']++;
        $count = (int)$data['count'];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($count > $maxPerMinute) {
            header('Retry-After: 60');
            if ($request->expectsJson()) {
                Response::jsonError('Terlalu banyak permintaan. Coba lagi dalam satu menit.', [], 429, [
                    'code' => 'rate_limited',
                ]);
            }
            Response::html('<!doctype html><meta charset="utf-8"><title>429</title><p style="font-family:system-ui;padding:2rem">Terlalu banyak permintaan. Coba lagi dalam satu menit.</p>', 429);
        }
    }
}
