<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\HttpException;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Validators\AssignmentValidator;

final class AssignmentService
{
    /**
     * Membuat penugasan baru dengan proteksi konkurensi database transaction & lock.
     */
    public static function create(array $data, ?int $userId = null): array
    {
        $errors = AssignmentValidator::validate($data);
        if ($errors !== []) {
            throw new HttpException(422, 'Data penugasan tidak valid.', $errors);
        }

        $vehicleId = (int)$data['vehicle_id'];
        $driverId = (int)$data['driver_id'];
        $date = $data['assignment_date'];

        // Concurrency protection: lock rows and check conflicts within transaction
        $newId = DB::transaction(function (\PDO $pdo) use ($data, $vehicleId, $driverId, $date, $userId) {
            // Lock target vehicle and driver
            $vehStmt = $pdo->prepare('SELECT id, status, plate_number FROM vehicles WHERE id = ? FOR UPDATE');
            $vehStmt->execute([$vehicleId]);
            $veh = $vehStmt->fetch();

            if (!$veh || $veh['status'] !== Vehicle::STATUS_ACTIVE) {
                throw new HttpException(422, 'Kendaraan tidak aktif atau tidak ditemukan.');
            }

            $drvStmt = $pdo->prepare('SELECT id, status, license_expiry FROM drivers WHERE id = ? FOR UPDATE');
            $drvStmt->execute([$driverId]);
            $drv = $drvStmt->fetch();

            if (!$drv || $drv['status'] !== Driver::STATUS_ACTIVE) {
                throw new HttpException(422, 'Driver tidak aktif atau tidak ditemukan.');
            }

            if ($drv['license_expiry'] < $date) {
                throw new HttpException(422, 'Masa berlaku SIM driver telah habis pada tanggal penugasan.');
            }

            // Check conflicting active assignments on the same date
            $vConfStmt = $pdo->prepare('SELECT id, assignment_number FROM assignments WHERE vehicle_id = ? AND assignment_date = ? AND status = ? LIMIT 1 FOR UPDATE');
            $vConfStmt->execute([$vehicleId, $date, Assignment::STATUS_ASSIGNED]);
            $vConflict = $vConfStmt->fetch();
            if ($vConflict) {
                throw new HttpException(409, "Kendaraan sudah memiliki penugasan aktif pada tanggal {$date} ({$vConflict['assignment_number']}).", [
                    'vehicle_id' => 'Kendaraan sudah memiliki penugasan aktif di tanggal ini.',
                ]);
            }

            $dConfStmt = $pdo->prepare('SELECT id, assignment_number FROM assignments WHERE driver_id = ? AND assignment_date = ? AND status = ? LIMIT 1 FOR UPDATE');
            $dConfStmt->execute([$driverId, $date, Assignment::STATUS_ASSIGNED]);
            $dConflict = $dConfStmt->fetch();
            if ($dConflict) {
                throw new HttpException(409, "Driver sudah memiliki penugasan aktif pada tanggal {$date} ({$dConflict['assignment_number']}).", [
                    'driver_id' => 'Driver sudah memiliki penugasan aktif di tanggal ini.',
                ]);
            }

            // Server-side assignment number generation
            $asgNumber = Assignment::generateNumber($date);
            $data['assignment_number'] = $asgNumber;
            $data['created_by'] = $userId;

            return Assignment::create($data);
        });

        $created = Assignment::find($newId);

        AuditService::log('ASSIGNMENT_CREATED', 'assignment', (string)$newId, null, [
            'assignment_number' => $created['assignment_number'] ?? '',
            'assignment_date' => $date,
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'status' => $data['status'] ?? Assignment::STATUS_ASSIGNED,
        ]);

        return $created ?? ['id' => $newId];
    }

    /**
     * Update penugasan dengan validasi & proteksi konflik.
     */
    public static function update(int $id, array $data, ?int $userId = null): array
    {
        $existing = Assignment::find($id);
        if ($existing === null) {
            throw new HttpException(404, 'Penugasan tidak ditemukan.');
        }

        if ($existing['status'] === Assignment::STATUS_CANCELLED) {
            throw new HttpException(422, 'Penugasan yang sudah dibatalkan tidak dapat diubah.');
        }

        $merged = array_merge($existing, $data);
        $errors = AssignmentValidator::validate($merged, $id);
        if ($errors !== []) {
            throw new HttpException(422, 'Data penugasan tidak valid.', $errors);
        }

        $vehicleId = (int)$merged['vehicle_id'];
        $driverId = (int)$merged['driver_id'];
        $date = $merged['assignment_date'];

        DB::transaction(function (\PDO $pdo) use ($id, $vehicleId, $driverId, $date) {
            // Lock vehicle and driver
            $pdo->prepare('SELECT id FROM vehicles WHERE id = ? FOR UPDATE')->execute([$vehicleId]);
            $pdo->prepare('SELECT id FROM drivers WHERE id = ? FOR UPDATE')->execute([$driverId]);

            // Conflict check excluding current assignment
            $vConfStmt = $pdo->prepare('SELECT id, assignment_number FROM assignments WHERE vehicle_id = ? AND assignment_date = ? AND status = ? AND id != ? LIMIT 1 FOR UPDATE');
            $vConfStmt->execute([$vehicleId, $date, Assignment::STATUS_ASSIGNED, $id]);
            $vConflict = $vConfStmt->fetch();
            if ($vConflict) {
                throw new HttpException(409, "Kendaraan sudah memiliki penugasan aktif pada tanggal {$date} ({$vConflict['assignment_number']}).", [
                    'vehicle_id' => 'Kendaraan sudah memiliki penugasan aktif di tanggal ini.',
                ]);
            }

            $dConfStmt = $pdo->prepare('SELECT id, assignment_number FROM assignments WHERE driver_id = ? AND assignment_date = ? AND status = ? AND id != ? LIMIT 1 FOR UPDATE');
            $dConfStmt->execute([$driverId, $date, Assignment::STATUS_ASSIGNED, $id]);
            $dConflict = $dConfStmt->fetch();
            if ($dConflict) {
                throw new HttpException(409, "Driver sudah memiliki penugasan aktif pada tanggal {$date} ({$dConflict['assignment_number']}).", [
                    'driver_id' => 'Driver sudah memiliki penugasan aktif di tanggal ini.',
                ]);
            }
        });

        Assignment::update($id, $data);
        $updated = Assignment::find($id);

        AuditService::log('ASSIGNMENT_UPDATED', 'assignment', (string)$id, [
            'vehicle_id' => $existing['vehicle_id'],
            'driver_id' => $existing['driver_id'],
            'status' => $existing['status'],
        ], [
            'vehicle_id' => $updated['vehicle_id'] ?? $vehicleId,
            'driver_id' => $updated['driver_id'] ?? $driverId,
            'status' => $updated['status'] ?? $existing['status'],
        ]);

        return $updated ?? ['id' => $id];
    }

    /**
     * Membatalkan penugasan.
     */
    public static function cancel(int $id, ?string $reason = null, ?int $userId = null): array
    {
        $existing = Assignment::find($id);
        if ($existing === null) {
            throw new HttpException(404, 'Penugasan tidak ditemukan.');
        }

        if ($existing['status'] === Assignment::STATUS_CANCELLED) {
            throw new HttpException(422, 'Penugasan sudah berstatus batal.');
        }

        Assignment::cancel($id, $reason);
        $updated = Assignment::find($id);

        AuditService::log('ASSIGNMENT_CANCELLED', 'assignment', (string)$id, [
            'status' => $existing['status'],
        ], [
            'status' => Assignment::STATUS_CANCELLED,
            'reason' => $reason,
        ]);

        return $updated ?? ['id' => $id];
    }
}
