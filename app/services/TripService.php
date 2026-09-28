<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\HttpException;
use App\Models\AuditLog;
use App\Models\Trip;
use App\Validators\TripGpsValidator;
use PDO;
use PDOException;

/** Authoritative owner for trip creation, authorization, idempotency and transitions. */
final class TripService
{
    private const EVENT_BY_TARGET = [
        'READY' => 'READY',
        'STARTED' => 'START',
        'ARRIVED' => 'ARRIVAL',
        'RETURNING' => 'RETURNING',
        'COMPLETED' => 'COMPLETED',
        'SUBMITTED' => 'SUBMITTED',
    ];

    private const TIMESTAMP_BY_TARGET = [
        'STARTED' => 'actual_started_at',
        'ARRIVED' => 'actual_arrived_at',
        'RETURNING' => 'actual_returning_at',
        'COMPLETED' => 'actual_completed_at',
        'SUBMITTED' => 'actual_submitted_at',
    ];

    public static function create(int $assignmentId, string $actionUuid, array $actor, array $options = []): array
    {
        self::requireRole($actor, ['admin', 'operator']);
        self::requireUuid($actionUuid);
        $plannedAt = self::plannedDeparture($options['planned_departure_at'] ?? null);
        $notes = self::notes($options['notes'] ?? null);
        $actorId = self::actorId($actor);

        try {
            $id = DB::transaction(function (PDO $pdo) use ($assignmentId, $actionUuid, $actorId, $plannedAt, $notes): array {
                $existing = $pdo->prepare('SELECT id, assignment_id, created_by, planned_departure_at, notes FROM trip WHERE create_uuid = ? FOR UPDATE');
                $existing->execute([$actionUuid]);
                $duplicate = $existing->fetch();
                if ($duplicate) {
                    if ((int)$duplicate['created_by'] !== $actorId
                        || (int)$duplicate['assignment_id'] !== $assignmentId
                        || (string)($duplicate['planned_departure_at'] ?? '') !== (string)($plannedAt ?? '')
                        || (string)($duplicate['notes'] ?? '') !== (string)($notes ?? '')) {
                        throw new HttpException(409, 'Kunci idempotensi telah digunakan untuk payload yang berbeda.');
                    }
                    return ['id' => (int)$duplicate['id'], 'idempotent' => true];
                }

                $stmt = $pdo->prepare('SELECT * FROM assignments WHERE id = ? FOR UPDATE');
                $stmt->execute([$assignmentId]);
                $assignment = $stmt->fetch();
                if (!$assignment) {
                    throw new HttpException(404, 'Penugasan tidak ditemukan.');
                }
                if ($assignment['status'] !== 'ASSIGNED') {
                    throw new HttpException(422, 'Trip hanya dapat dibuat dari penugasan berstatus ASSIGNED.');
                }

                $vehicleStmt = $pdo->prepare('SELECT id, status FROM vehicles WHERE id = ? FOR UPDATE');
                $vehicleStmt->execute([(int)$assignment['vehicle_id']]);
                $vehicle = $vehicleStmt->fetch();
                if (!$vehicle || $vehicle['status'] !== 'ACTIVE') {
                    throw new HttpException(422, 'Kendaraan pada penugasan tidak aktif atau tidak ditemukan.');
                }

                $driverStmt = $pdo->prepare('SELECT id, status, license_expiry FROM drivers WHERE id = ? FOR UPDATE');
                $driverStmt->execute([(int)$assignment['driver_id']]);
                $driver = $driverStmt->fetch();
                if (!$driver || $driver['status'] !== 'ACTIVE') {
                    throw new HttpException(422, 'Driver pada penugasan tidak aktif atau tidak ditemukan.');
                }
                if ((string)$driver['license_expiry'] < (string)$assignment['assignment_date']) {
                    throw new HttpException(422, 'SIM driver telah kedaluwarsa pada tanggal penugasan.');
                }

                $year = (int)(new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y');
                $seq = $pdo->prepare('INSERT IGNORE INTO trip_number_sequences (trip_year, last_value) VALUES (?, 0)');
                $seq->execute([$year]);
                $seqLock = $pdo->prepare('SELECT last_value FROM trip_number_sequences WHERE trip_year = ? FOR UPDATE');
                $seqLock->execute([$year]);
                $number = (int)$seqLock->fetchColumn() + 1;
                if ($number > 99999) {
                    throw new HttpException(409, 'Nomor trip untuk tahun ini telah mencapai batas.');
                }
                $pdo->prepare('UPDATE trip_number_sequences SET last_value = ? WHERE trip_year = ?')->execute([$number, $year]);
                $tripNumber = sprintf('TRP-%04d-%05d', $year, $number);

                $insert = $pdo->prepare(
                    'INSERT INTO trip (trip_number, assignment_id, create_uuid, vehicle_id, driver_id,
                                       destination_latitude, destination_longitude, status, planned_departure_at,
                                       notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, \'ASSIGNED\', ?, ?, ?)'
                );
                $insert->execute([
                    $tripNumber,
                    $assignmentId,
                    $actionUuid,
                    (int)$assignment['vehicle_id'],
                    (int)$assignment['driver_id'],
                    $assignment['destination_latitude'],
                    $assignment['destination_longitude'],
                    $plannedAt,
                    $notes,
                    $actorId,
                ]);
                $tripId = (int)$pdo->lastInsertId();
                AuditLog::record($actorId, 'TRIP_CREATED', 'trip', (string)$tripId, null, [
                    'trip_number' => $tripNumber,
                    'assignment_id' => $assignmentId,
                    'vehicle_id' => (int)$assignment['vehicle_id'],
                    'driver_id' => (int)$assignment['driver_id'],
                    'destination_coordinates_set' => $assignment['destination_latitude'] !== null
                        && $assignment['destination_longitude'] !== null,
                    'status' => 'ASSIGNED',
                ]);
                return ['id' => $tripId, 'idempotent' => false];
            });
        } catch (PDOException $e) {
            if (self::isConstraintViolation($e)) {
                throw new HttpException(409, 'Trip untuk penugasan ini sudah dibuat atau kunci idempotensi telah digunakan.');
            }
            throw $e;
        }

        $trip = self::findAuthorized((int)$id['id'], $actor);
        return ['trip' => $trip, 'idempotent' => (bool)$id['idempotent']];
    }

    public static function ready(int $tripId, string $actionUuid, array $actor, array $input = []): array
    {
        return self::transition($tripId, 'READY', $actionUuid, $actor, $input);
    }

    public static function start(int $tripId, string $actionUuid, array $actor, array $input = []): array
    {
        return self::transition($tripId, 'STARTED', $actionUuid, $actor, $input);
    }

    public static function arrival(int $tripId, string $actionUuid, array $actor, array $input = []): array
    {
        return self::transition($tripId, 'ARRIVED', $actionUuid, $actor, $input);
    }

    public static function returning(int $tripId, string $actionUuid, array $actor, array $input = []): array
    {
        return self::transition($tripId, 'RETURNING', $actionUuid, $actor, $input);
    }

    public static function complete(int $tripId, string $actionUuid, array $actor, array $input = []): array
    {
        return self::transition($tripId, 'COMPLETED', $actionUuid, $actor, $input);
    }

    public static function submit(int $tripId, string $actionUuid, array $actor, array $input = []): array
    {
        return self::transition($tripId, 'SUBMITTED', $actionUuid, $actor, $input);
    }

    public static function transition(int $tripId, string $targetStatus, string $actionUuid, array $actor, array $input = []): array
    {
        self::requireUuid($actionUuid);
        if (!isset(self::EVENT_BY_TARGET[$targetStatus])) {
            throw new HttpException(422, 'Aksi trip tidak dikenal.');
        }
        $notes = self::notes($input['notes'] ?? null);
        $actorId = self::actorId($actor);
        $eventType = self::EVENT_BY_TARGET[$targetStatus];

        try {
            $result = DB::transaction(function (PDO $pdo) use ($tripId, $targetStatus, $actionUuid, $actor, $actorId, $eventType, $input, $notes): array {
                $lock = $pdo->prepare('SELECT * FROM trip WHERE id = ? FOR UPDATE');
                $lock->execute([$tripId]);
                $trip = $lock->fetch();
                if (!$trip) {
                    throw new HttpException(404, 'Trip tidak ditemukan.');
                }
                self::authorizeTrip($pdo, $trip, $actor, true);

                $gps = null;
                if (in_array($eventType, ['START', 'ARRIVAL', 'COMPLETED'], true)) {
                    $gps = TripGpsValidator::evaluate(
                        $input['latitude'] ?? null,
                        $input['longitude'] ?? null,
                        $input['accuracy_m'] ?? null,
                        $eventType,
                        $trip['destination_latitude'],
                        $trip['destination_longitude']
                    );
                }

                $existingStmt = $pdo->prepare('SELECT * FROM trip_events WHERE event_uuid = ?');
                $existingStmt->execute([$actionUuid]);
                $existingEvent = $existingStmt->fetch();
                if ($existingEvent) {
                    if ((int)$existingEvent['trip_id'] !== $tripId
                        || $existingEvent['event_type'] !== $eventType
                        || (int)($existingEvent['actor_user_id'] ?? 0) !== $actorId
                        || !self::eventPayloadMatches($existingEvent, $gps, $notes)) {
                        throw new HttpException(409, 'Kunci idempotensi telah digunakan oleh aksi atau payload yang berbeda.');
                    }
                    return ['trip_id' => $tripId, 'event_id' => (int)$existingEvent['id'], 'idempotent' => true];
                }

                if (!TripStateMachine::allows((string)$trip['status'], $targetStatus)) {
                    throw new HttpException(409, "Transisi trip tidak valid: {$trip['status']} → {$targetStatus}.", [
                        'current_status' => (string)$trip['status'],
                        'requested_action' => $eventType,
                    ]);
                }

                $metadata = [];
                if ($gps !== null) {
                    $metadata['gps_reason'] = $gps['reason'];
                }
                $clientTimestamp = $input['client_timestamp'] ?? null;
                if (is_scalar($clientTimestamp) && trim((string)$clientTimestamp) !== '') {
                    $metadata['client_timestamp_untrusted'] = mb_substr(trim((string)$clientTimestamp), 0, 40);
                }
                $metadataJson = $metadata !== []
                    ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null;

                $eventStmt = $pdo->prepare(
                    'INSERT INTO trip_events (trip_id, event_type, event_uuid, latitude, longitude,
                                             accuracy_m, distance_to_destination_m, location_status, notes,
                                             actor_user_id, metadata_json)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $eventStmt->execute([
                    $tripId,
                    $eventType,
                    $actionUuid,
                    $gps['latitude'] ?? null,
                    $gps['longitude'] ?? null,
                    $gps['accuracy_m'] ?? null,
                    $gps['distance_to_destination_m'] ?? null,
                    $gps['location_status'] ?? null,
                    $notes,
                    $actorId,
                    $metadataJson,
                ]);
                $eventId = (int)$pdo->lastInsertId();

                $timestampColumn = self::TIMESTAMP_BY_TARGET[$targetStatus] ?? null;
                $updateSql = $timestampColumn === null
                    ? 'UPDATE trip SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
                    : "UPDATE trip SET status = ?, `{$timestampColumn}` = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $pdo->prepare($updateSql)->execute([$targetStatus, $tripId]);

                $auditAction = match ($targetStatus) {
                    'READY' => 'TRIP_READY',
                    'STARTED' => 'TRIP_STARTED',
                    'ARRIVED' => 'TRIP_ARRIVED',
                    'RETURNING' => 'TRIP_RETURNING',
                    'COMPLETED' => 'TRIP_COMPLETED',
                    'SUBMITTED' => 'TRIP_SUBMITTED',
                };
                $newData = ['status' => $targetStatus, 'event_id' => $eventId, 'event_uuid' => $actionUuid];
                if ($gps !== null) {
                    $newData['location_status'] = $gps['location_status'];
                    $newData['distance_to_destination_m'] = $gps['distance_to_destination_m'];
                }
                AuditLog::record($actorId, $auditAction, 'trip', (string)$tripId, [
                    'status' => (string)$trip['status'],
                ], $newData);

                return ['trip_id' => $tripId, 'event_id' => $eventId, 'idempotent' => false];
            });
        } catch (PDOException $e) {
            if (self::isConstraintViolation($e)) {
                throw new HttpException(409, 'Aksi duplikat terdeteksi; muat ulang status trip.');
            }
            throw $e;
        }

        return [
            'trip' => self::findAuthorized($tripId, $actor),
            'event_id' => $result['event_id'],
            'idempotent' => $result['idempotent'],
        ];
    }

    public static function findAuthorized(int $tripId, array $actor): array
    {
        $trip = Trip::find($tripId);
        if ($trip === null) {
            throw new HttpException(404, 'Trip tidak ditemukan.');
        }
        self::authorizeTrip(DB::pdo(), $trip, $actor, false);
        $trip['events'] = Trip::events($tripId);
        return $trip;
    }

    public static function listForActor(array $actor, int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        self::actorId($actor);
        $role = strtolower((string)($actor['role'] ?? ''));
        if (!in_array($role, ['admin', 'operator', 'driver', 'pimpinan'], true)) {
            throw new HttpException(403, 'Anda tidak memiliki akses ke trip.');
        }
        if ($status !== null && $status !== '' && !in_array($status, Trip::VALID_STATUSES, true)) {
            throw new HttpException(422, 'Filter status trip tidak valid.', ['status' => 'Status tidak dikenal.']);
        }
        $driverId = null;
        if ($role === 'driver') {
            $stmt = DB::run('SELECT id FROM drivers WHERE user_id = ?', [self::actorId($actor)]);
            $driverId = $stmt->fetchColumn();
            if ($driverId === false) {
                return ['data' => [], 'total' => 0, 'page' => max(1, $page), 'per_page' => min(100, max(1, $perPage)), 'last_page' => 1];
            }
            $driverId = (int)$driverId;
        }
        return Trip::paginate(max(1, $page), min(100, max(1, $perPage)), $status, $driverId);
    }

    private static function authorizeTrip(PDO $pdo, array $trip, array $actor, bool $write): void
    {
        $actorId = self::actorId($actor);
        $role = strtolower((string)($actor['role'] ?? ''));
        if (in_array($role, ['admin', 'operator'], true)) {
            return;
        }
        if ($role === 'driver') {
            $stmt = $pdo->prepare('SELECT user_id FROM drivers WHERE id = ?');
            $stmt->execute([(int)$trip['driver_id']]);
            $ownerId = $stmt->fetchColumn();
            if ($ownerId === false || (int)$ownerId !== $actorId) {
                // Hide another driver's object to reduce identifier enumeration.
                throw new HttpException(404, 'Trip tidak ditemukan.');
            }
            return;
        }
        if (!$write && $role === 'pimpinan') {
            return;
        }
        throw new HttpException(403, 'Aksi trip tidak diizinkan untuk role ini.');
    }

    private static function requireRole(array $actor, array $allowed): void
    {
        $role = strtolower((string)($actor['role'] ?? ''));
        self::actorId($actor);
        if (!in_array($role, $allowed, true)) {
            throw new HttpException(403, 'Anda tidak memiliki akses untuk mengelola trip.');
        }
    }

    private static function actorId(array $actor): int
    {
        $id = (int)($actor['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException(401, 'Sesi pengguna tidak valid.');
        }
        return $id;
    }

    private static function requireUuid(string $uuid): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            throw new HttpException(422, 'action_uuid wajib berupa UUID yang valid.', ['action_uuid' => 'UUID tidak valid.']);
        }
    }

    private static function plannedDeparture(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new HttpException(422, 'planned_departure_at tidak valid.');
        }
        if (trim((string)$value) === '') {
            return null;
        }
        $value = trim((string)$value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new \DateTimeZone('Asia/Jakarta'));
        if (!$date || $date->format('Y-m-d\TH:i') !== $value) {
            throw new HttpException(422, 'planned_departure_at harus berformat YYYY-MM-DDTHH:MM.');
        }
        return $date->format('Y-m-d H:i:s');
    }

    private static function notes(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value)) {
            throw new HttpException(422, 'Catatan tidak valid.');
        }
        $text = trim((string)$value);
        if (mb_strlen($text) > 1000) {
            throw new HttpException(422, 'Catatan maksimal 1000 karakter.');
        }
        return $text === '' ? null : $text;
    }

    private static function eventPayloadMatches(array $event, ?array $gps, ?string $notes): bool
    {
        $expected = [
            'latitude' => $gps['latitude'] ?? null,
            'longitude' => $gps['longitude'] ?? null,
            'accuracy_m' => $gps['accuracy_m'] ?? null,
            'distance_to_destination_m' => $gps['distance_to_destination_m'] ?? null,
            'location_status' => $gps['location_status'] ?? null,
        ];
        $scales = ['latitude' => 7, 'longitude' => 7, 'accuracy_m' => 2, 'distance_to_destination_m' => 2];
        foreach ($scales as $field => $scale) {
            $wanted = $expected[$field];
            $stored = $event[$field] ?? null;
            if ($wanted === null ? $stored !== null : ($stored === null || number_format((float)$stored, $scale, '.', '') !== number_format(round((float)$wanted, $scale), $scale, '.', ''))) {
                return false;
            }
        }
        return ($event['location_status'] ?? null) === $expected['location_status']
            && (string)($event['notes'] ?? '') === (string)($notes ?? '');
    }

    private static function isConstraintViolation(PDOException $e): bool
    {
        return (string)$e->getCode() === '23000' || (string)($e->errorInfo[0] ?? '') === '23000';
    }
}
