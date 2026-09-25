<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\Ambulance;
use App\Validators\AmbulanceValidator;

final class AmbulanceService
{
    public static function create(array $data, ?int $userId = null): array
    {
        $errors = AmbulanceValidator::validate($data, null, true);
        if ($errors !== []) {
            throw new HttpException(422, 'Data profil ambulans tidak valid.', $errors);
        }

        Ambulance::create($data);
        $created = Ambulance::find((int)$data['vehicle_id']);

        AuditService::log('AMBULANCE_CREATED', 'ambulance', (string)$data['vehicle_id'], null, [
            'ambulance_code' => $data['ambulance_code'],
            'ambulance_name' => $data['ambulance_name'],
            'readiness' => $data['readiness'] ?? Ambulance::READINESS_READY,
        ]);

        return $created ?? ['vehicle_id' => $data['vehicle_id']];
    }

    public static function update(int $vehicleId, array $data, ?int $userId = null): array
    {
        $existing = Ambulance::find($vehicleId);
        if ($existing === null) {
            throw new HttpException(404, 'Profil ambulans tidak ditemukan.');
        }

        $errors = AmbulanceValidator::validate($data, $vehicleId, false);
        if ($errors !== []) {
            throw new HttpException(422, 'Data profil ambulans tidak valid.', $errors);
        }

        Ambulance::update($vehicleId, $data);
        $updated = Ambulance::find($vehicleId);

        AuditService::log('AMBULANCE_UPDATED', 'ambulance', (string)$vehicleId, [
            'ambulance_code' => $existing['ambulance_code'],
            'readiness' => $existing['readiness'],
        ], [
            'ambulance_code' => $updated['ambulance_code'] ?? $data['ambulance_code'],
            'readiness' => $updated['readiness'] ?? $data['readiness'],
        ]);

        return $updated ?? ['vehicle_id' => $vehicleId];
    }

    public static function delete(int $vehicleId, ?int $userId = null): void
    {
        $existing = Ambulance::find($vehicleId);
        if ($existing === null) {
            throw new HttpException(404, 'Profil ambulans tidak ditemukan.');
        }

        Ambulance::delete($vehicleId);

        AuditService::log('AMBULANCE_DELETED', 'ambulance', (string)$vehicleId, [
            'ambulance_code' => $existing['ambulance_code'],
            'vehicle_id' => $vehicleId,
        ], null);
    }
}
