<?php

declare(strict_types=1);

/**
 * Helper global — dipanggil dari public/index.php.
 */

use App\Core\Session;

/** Konfigurasi (cached). config('app.timezone') atau config(). */
function config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require BASE_PATH . '/app/config/config.php';
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/** Escape output (XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL path relatif. */
function url(string $path = ''): string
{
    if ($path === '') {
        return '/';
    }
    return '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function csrf_token(): string
{
    return Session::csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Session::csrfToken()) . '">';
}

function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . e(Session::csrfToken()) . '">';
}

function redirect(string $to): never
{
    \App\Core\Response::redirect($to);
}

/** Nilai setting aplikasi (dari tabel settings, cache per-request). */
function setting(string $key, mixed $default = null): mixed
{
    return \App\Services\SettingsService::get($key, $default);
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

/** @return array<int,array{type:string,message:string}> */
function flashes(): array
{
    return Session::pullFlash();
}

function auth_user(): ?array
{
    return Session::user();
}

/** Sapaan waktu (Asia/Jakarta sudah di-set sebagai default timezone). */
function greeting(): string
{
    $h = (int)date('G');
    if ($h < 4) {
        return 'Selamat malam';
    }
    if ($h < 11) {
        return 'Selamat pagi';
    }
    if ($h < 15) {
        return 'Selamat siang';
    }
    if ($h < 18) {
        return 'Selamat sore';
    }
    return 'Selamat malam';
}

/** Format angka ribuan: 12345 → 12.345 */
function fmt_int(int|float $n): string
{
    return number_format($n, 0, ',', '.');
}

/** Format Rupiah. */
function fmt_idr(int|float $n): string
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}
