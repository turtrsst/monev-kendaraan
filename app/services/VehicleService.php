<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\Vehicle;
use App\Validators\VehicleValidator;

final class VehicleService
{
    public static function create(array $data, ?int $userId = null): array
    {
        $errors = VehicleValidator::validate($data);
        if ($errors !== []) {
            throw new HttpException(422, 'Data kendaraan tidak valid.', $errors);
        }

        $id = Vehicle::create($data);
        $created = Vehicle::find($id);

        AuditService::log('VEHICLE_CREATED', 'vehicle', (string)$id, null, [
            'vehicle_code' => $data['vehicle_code'],
            'plate_number' => $data['plate_number'],
            'vehicle_name' => $data['vehicle_name'],
            'status' => $data['status'] ?? Vehicle::STATUS_ACTIVE,
        ]);

        return $created ?? ['id' => $id];
    }

    public static function update(int $id, array $data, ?int $userId = null): array
    {
        $existing = Vehicle::find($id);
        if ($existing === null) {
            throw new HttpException(404, 'Kendaraan tidak ditemukan.');
        }

        $errors = VehicleValidator::validate($data, $id);
        if ($errors !== []) {
            throw new HttpException(422, 'Data kendaraan tidak valid.', $errors);
        }

        Vehicle::update($id, $data);
        $updated = Vehicle::find($id);

        AuditService::log('VEHICLE_UPDATED', 'vehicle', (string)$id, [
            'plate_number' => $existing['plate_number'],
            'status' => $existing['status'],
        ], [
            'plate_number' => $updated['plate_number'] ?? $data['plate_number'],
            'status' => $updated['status'] ?? $data['status'],
        ]);

        return $updated ?? ['id' => $id];
    }

    public static function delete(int $id, ?int $userId = null): void
    {
        $existing = Vehicle::find($id);
        if ($existing === null) {
            throw new HttpException(404, 'Kendaraan tidak ditemukan.');
        }

        Vehicle::delete($id);

        AuditService::log('VEHICLE_DELETED', 'vehicle', (string)$id, [
            'vehicle_code' => $existing['vehicle_code'],
            'plate_number' => $existing['plate_number'],
        ], null);
    }
}
