<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Ambulance;
use App\Models\Vehicle;

final class AmbulanceValidator
{
    /** @return array<string,string> errors */
    public static function validate(array $data, ?int $vehicleId = null, bool $isCreate = false): array
    {
        $errors = [];

        // vehicle_id (must exist in vehicles and not already have ambulance details if create)
        if ($isCreate) {
            $vid = isset($data['vehicle_id']) ? (int)$data['vehicle_id'] : 0;
            if ($vid <= 0) {
                $errors['vehicle_id'] = 'Pilihan kendaraan master wajib diisi.';
            } else {
                $veh = Vehicle::find($vid);
                if ($veh === null) {
                    $errors['vehicle_id'] = 'Kendaraan tidak ditemukan di sistem.';
                } else {
                    $existingAmb = Ambulance::find($vid);
                    if ($existingAmb !== null) {
                        $errors['vehicle_id'] = 'Kendaraan ini sudah memiliki profil ambulans.';
                    }
                }
            }
        }

        // ambulance_code
        $code = trim((string)($data['ambulance_code'] ?? ''));
        if ($code === '') {
            $errors['ambulance_code'] = 'Kode ambulans wajib diisi.';
        } elseif (strlen($code) < 2 || strlen($code) > 32) {
            $errors['ambulance_code'] = 'Kode ambulans harus 2–32 karakter.';
        } elseif (!preg_match('/^[A-Za-z0-9_\-]+$/', $code)) {
            $errors['ambulance_code'] = 'Kode ambulans hanya boleh huruf, angka, minus, dan underscore.';
        } else {
            $existing = Ambulance::findByCode($code);
            if ($existing !== null && ($isCreate || (int)$existing['vehicle_id'] !== $vehicleId)) {
                $errors['ambulance_code'] = 'Kode ambulans sudah digunakan.';
            }
        }

        // ambulance_name
        $name = trim((string)($data['ambulance_name'] ?? ''));
        if ($name === '') {
            $errors['ambulance_name'] = 'Nama unit ambulans wajib diisi.';
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $errors['ambulance_name'] = 'Nama unit ambulans harus 2–100 karakter.';
        }

        // base_location
        $loc = trim((string)($data['base_location'] ?? ''));
        if ($loc === '') {
            $errors['base_location'] = 'Lokasi pangkalan / posko ambulans wajib diisi.';
        }

        // readiness
        $readiness = (string)($data['readiness'] ?? Ambulance::READINESS_READY);
        if (!in_array($readiness, Ambulance::VALID_READINESS, true)) {
            $errors['readiness'] = 'Status kesiapan tidak valid. Pilihan: ' . implode(', ', Ambulance::VALID_READINESS);
        }

        // date validations
        foreach (['stnk_expiry', 'kir_expiry', 'insurance_expiry'] as $dateField) {
            if (!empty($data[$dateField]) && !self::isValidDate((string)$data[$dateField])) {
                $errors[$dateField] = "Format tanggal {$dateField} tidak valid (YYYY-MM-DD).";
            }
        }

        return $errors;
    }

    private static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
