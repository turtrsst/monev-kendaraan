<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Validasi server-side untuk autentikasi.
 * @phpstan-type Errors array<string,string>
 */
final class AuthValidator
{
    /** @return array<string,string> errors (kosong = valid) */
    public static function login(string $username, string $password): array
    {
        $errors = [];
        if ($username === '') {
            $errors['username'] = 'Username wajib diisi.';
        } elseif (strlen($username) < 3 || strlen($username) > 64) {
            $errors['username'] = 'Username harus 3–64 karakter.';
        } elseif (!preg_match('/^[A-Za-z0-9._\-]+$/', $username)) {
            $errors['username'] = 'Username hanya boleh huruf, angka, titik, underscore, dan tanda minus.';
        }
        if ($password === '') {
            $errors['password'] = 'Password wajib diisi.';
        } elseif (strlen($password) > 1024) {
            $errors['password'] = 'Password terlalu panjang.';
        }
        return $errors;
    }

    /** @return array<string,string> errors */
    public static function changePassword(string $current, string $new, string $confirm, string $currentHash): array
    {
        $errors = [];
        if ($current === '') {
            $errors['current'] = 'Password saat ini wajib diisi.';
        } elseif (!password_verify($current, $currentHash)) {
            $errors['current'] = 'Password saat ini tidak sesuai.';
        }
        if ($new === '') {
            $errors['new'] = 'Password baru wajib diisi.';
        } elseif (strlen($new) < 8) {
            $errors['new'] = 'Password baru minimal 8 karakter.';
        } elseif (strlen($new) > 1024) {
            $errors['new'] = 'Password baru terlalu panjang.';
        } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $errors['new'] = 'Password baru harus mengandung huruf dan angka.';
        } elseif (password_verify($new, $currentHash)) {
            $errors['new'] = 'Password baru tidak boleh sama dengan password saat ini.';
        }
        if ($confirm !== $new) {
            $errors['confirm'] = 'Konfirmasi password tidak sama.';
        }
        return $errors;
    }
}
