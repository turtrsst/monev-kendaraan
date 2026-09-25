<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Driver;

final class DriverValidator
{
    /** @return array<string,string> errors */
    public static function validate(array $data, ?int $ignoreId = null): array
    {
        $errors = [];

        // driver_code
        $code = trim((string)($data['driver_code'] ?? ''));
        if ($code === '') {
            $errors['driver_code'] = 'Kode driver wajib diisi.';
        } elseif (strlen($code) < 3 || strlen($code) > 32) {
            $errors['driver_code'] = 'Kode driver harus 3–32 karakter.';
        } elseif (!preg_match('/^[A-Za-z0-9_\-]+$/', $code)) {
            $errors['driver_code'] = 'Kode driver hanya boleh huruf, angka, minus, dan underscore.';
        } else {
            $existing = Driver::findByCode($code);
            if ($existing !== null && ($ignoreId === null || (int)$existing['id'] !== $ignoreId)) {
                $errors['driver_code'] = 'Kode driver sudah digunakan.';
            }
        }

        // name
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Nama lengkap driver wajib diisi.';
        } elseif (strlen($name) < 2 || strlen($name) > 120) {
            $errors['name'] = 'Nama driver harus 2–120 karakter.';
        }

        // phone
        $phone = trim((string)($data['phone'] ?? ''));
        if ($phone === '') {
            $errors['phone'] = 'Nomor HP/telepon wajib diisi.';
        } elseif (strlen($phone) < 8 || strlen($phone) > 32) {
            $errors['phone'] = 'Nomor HP/telepon harus 8–32 karakter.';
        }

        // license_number
        $licNum = trim((string)($data['license_number'] ?? ''));
        if ($licNum === '') {
            $errors['license_number'] = 'Nomor SIM wajib diisi.';
        } elseif (strlen($licNum) < 5 || strlen($licNum) > 50) {
            $errors['license_number'] = 'Nomor SIM harus 5–50 karakter.';
        }

        // license_expiry
        $licExp = (string)($data['license_expiry'] ?? '');
        if ($licExp === '') {
            $errors['license_expiry'] = 'Masa berlaku SIM wajib diisi.';
        } elseif (!self::isValidDate($licExp)) {
            $errors['license_expiry'] = 'Format masa berlaku SIM tidak valid (YYYY-MM-DD).';
        }

        // user_id reference duplicate check
        if (!empty($data['user_id'])) {
            $uid = (int)$data['user_id'];
            $existingUser = Driver::findByUserId($uid);
            if ($existingUser !== null && ($ignoreId === null || (int)$existingUser['id'] !== $ignoreId)) {
                $errors['user_id'] = 'User akun tersebut sudah terhubung dengan profil driver lain.';
            }
        }

        // status
        $status = (string)($data['status'] ?? Driver::STATUS_ACTIVE);
        if (!in_array($status, Driver::VALID_STATUSES, true)) {
            $errors['status'] = 'Status driver tidak valid. Pilihan: ' . implode(', ', Driver::VALID_STATUSES);
        }

        return $errors;
    }

    private static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
