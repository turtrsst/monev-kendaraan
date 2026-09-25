<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\Driver;
use App\Validators\DriverValidator;

final class DriverService
{
    public static function create(array $data, ?int $userId = null): array
    {
        $errors = DriverValidator::validate($data);
        if ($errors !== []) {
            throw new HttpException(422, 'Data driver tidak valid.', $errors);
        }

        $id = Driver::create($data);
        $created = Driver::find($id);

        AuditService::log('DRIVER_CREATED', 'driver', (string)$id, null, [
            'driver_code' => $data['driver_code'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'status' => $data['status'] ?? Driver::STATUS_ACTIVE,
        ]);

        return $created ?? ['id' => $id];
    }

    public static function update(int $id, array $data, ?int $userId = null): array
    {
        $existing = Driver::find($id);
        if ($existing === null) {
            throw new HttpException(404, 'Driver tidak ditemukan.');
        }

        $errors = DriverValidator::validate($data, $id);
        if ($errors !== []) {
            throw new HttpException(422, 'Data driver tidak valid.', $errors);
        }

        Driver::update($id, $data);
        $updated = Driver::find($id);

        AuditService::log('DRIVER_UPDATED', 'driver', (string)$id, [
            'name' => $existing['name'],
            'phone' => $existing['phone'],
            'status' => $existing['status'],
        ], [
            'name' => $updated['name'] ?? $data['name'],
            'phone' => $updated['phone'] ?? $data['phone'],
            'status' => $updated['status'] ?? $data['status'],
        ]);

        return $updated ?? ['id' => $id];
    }

    public static function delete(int $id, ?int $userId = null): void
    {
        $existing = Driver::find($id);
        if ($existing === null) {
            throw new HttpException(404, 'Driver tidak ditemukan.');
        }

        Driver::delete($id);

        AuditService::log('DRIVER_DELETED', 'driver', (string)$id, [
            'driver_code' => $existing['driver_code'],
            'name' => $existing['name'],
        ], null);
    }
}
