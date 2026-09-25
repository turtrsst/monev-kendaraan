<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Vehicle;

final class AssignmentValidator
{
    /** @return array<string,string> errors */
    public static function validate(array $data, ?int $ignoreId = null): array
    {
        $errors = [];

        // assignment_date
        $date = (string)($data['assignment_date'] ?? '');
        if ($date === '') {
            $errors['assignment_date'] = 'Tanggal penugasan wajib diisi.';
        } elseif (!self::isValidDate($date)) {
            $errors['assignment_date'] = 'Format tanggal penugasan tidak valid (YYYY-MM-DD).';
        }

        // vehicle_id
        $vehicleId = isset($data['vehicle_id']) ? (int)$data['vehicle_id'] : 0;
        if ($vehicleId <= 0) {
            $errors['vehicle_id'] = 'Kendaraan wajib dipilih.';
        } else {
            $veh = Vehicle::find($vehicleId);
            if ($veh === null) {
                $errors['vehicle_id'] = 'Kendaraan tidak ditemukan di sistem.';
            } elseif ($veh['status'] !== Vehicle::STATUS_ACTIVE) {
                $errors['vehicle_id'] = "Kendaraan tidak aktif (status: {$veh['status']}) dan tidak dapat ditugaskan.";
            }
        }

        // driver_id
        $driverId = isset($data['driver_id']) ? (int)$data['driver_id'] : 0;
        if ($driverId <= 0) {
            $errors['driver_id'] = 'Driver wajib dipilih.';
        } else {
            $drv = Driver::find($driverId);
            if ($drv === null) {
                $errors['driver_id'] = 'Driver tidak ditemukan di sistem.';
            } elseif ($drv['status'] !== Driver::STATUS_ACTIVE) {
                $errors['driver_id'] = "Driver berstatus {$drv['status']} dan tidak dapat ditugaskan.";
            } else {
                // License expiry check against assignment date or today
                $refDate = self::isValidDate($date) ? $date : date('Y-m-d');
                if ($drv['license_expiry'] < $refDate) {
                    $errors['driver_id'] = "SIM driver telah kadaluarsa ({$drv['license_expiry']}) per tanggal penugasan.";
                }
            }
        }

        // destination
        $dest = trim((string)($data['destination'] ?? ''));
        if ($dest === '') {
            $errors['destination'] = 'Tujuan perjalanan wajib diisi.';
        } elseif (strlen($dest) < 3 || strlen($dest) > 255) {
            $errors['destination'] = 'Tujuan perjalanan harus 3–255 karakter.';
        }

        // purpose
        $purpose = trim((string)($data['purpose'] ?? ''));
        if ($purpose === '') {
            $errors['purpose'] = 'Keperluan / maksud penugasan wajib diisi.';
        }

        // passenger_count
        if (isset($data['passenger_count'])) {
            $passCount = (int)$data['passenger_count'];
            if ($passCount < 0 || $passCount > 100) {
                $errors['passenger_count'] = 'Jumlah penumpang tidak valid (0–100).';
            }
        }

        // status
        if (isset($data['status'])) {
            $status = (string)$data['status'];
            if (!in_array($status, Assignment::VALID_STATUSES, true)) {
                $errors['status'] = 'Status penugasan tidak valid. Pilihan: ' . implode(', ', Assignment::VALID_STATUSES);
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
