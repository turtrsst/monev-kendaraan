<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Vehicle;

final class VehicleValidator
{
    /** @return array<string,string> errors */
    public static function validate(array $data, ?int $ignoreId = null): array
    {
        $errors = [];

        // vehicle_code
        $code = trim((string)($data['vehicle_code'] ?? ''));
        if ($code === '') {
            $errors['vehicle_code'] = 'Kode kendaraan wajib diisi.';
        } elseif (strlen($code) < 3 || strlen($code) > 32) {
            $errors['vehicle_code'] = 'Kode kendaraan harus 3–32 karakter.';
        } elseif (!preg_match('/^[A-Za-z0-9_\-]+$/', $code)) {
            $errors['vehicle_code'] = 'Kode kendaraan hanya boleh huruf, angka, minus, dan underscore.';
        } else {
            $existing = Vehicle::findByCode($code);
            if ($existing !== null && ($ignoreId === null || (int)$existing['id'] !== $ignoreId)) {
                $errors['vehicle_code'] = 'Kode kendaraan sudah digunakan.';
            }
        }

        // plate_number
        $plate = strtoupper(trim((string)($data['plate_number'] ?? '')));
        if ($plate === '') {
            $errors['plate_number'] = 'Nomor polisi (plat) wajib diisi.';
        } elseif (strlen($plate) < 3 || strlen($plate) > 20) {
            $errors['plate_number'] = 'Nomor polisi harus 3–20 karakter.';
        } else {
            $existingPlate = Vehicle::findByPlate($plate);
            if ($existingPlate !== null && ($ignoreId === null || (int)$existingPlate['id'] !== $ignoreId)) {
                $errors['plate_number'] = 'Nomor polisi sudah terdaftar.';
            }
        }

        // vehicle_name
        $name = trim((string)($data['vehicle_name'] ?? ''));
        if ($name === '') {
            $errors['vehicle_name'] = 'Nama / merk kendaraan wajib diisi.';
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $errors['vehicle_name'] = 'Nama kendaraan harus 2–100 karakter.';
        }

        // year
        if (isset($data['year']) && $data['year'] !== '') {
            $year = (int)$data['year'];
            $currentYear = (int)date('Y') + 1;
            if ($year < 1980 || $year > $currentYear) {
                $errors['year'] = "Tahun kendaraan tidak valid (1980–{$currentYear}).";
            }
        }

        // stnk_expiry & kir_expiry
        if (!empty($data['stnk_expiry'])) {
            if (!self::isValidDate((string)$data['stnk_expiry'])) {
                $errors['stnk_expiry'] = 'Format tanggal masa berlaku STNK tidak valid.';
            }
        }
        if (!empty($data['kir_expiry'])) {
            if (!self::isValidDate((string)$data['kir_expiry'])) {
                $errors['kir_expiry'] = 'Format tanggal masa berlaku KIR tidak valid.';
            }
        }

        // status
        $status = (string)($data['status'] ?? Vehicle::STATUS_ACTIVE);
        if (!in_array($status, Vehicle::VALID_STATUSES, true)) {
            $errors['status'] = 'Status kendaraan tidak valid. Pilihan: ' . implode(', ', Vehicle::VALID_STATUSES);
        }

        return $errors;
    }

    private static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
