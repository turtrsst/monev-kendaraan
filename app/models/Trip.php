<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Trip
{
    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_READY = 'READY';
    public const STATUS_STARTED = 'STARTED';
    public const STATUS_ARRIVED = 'ARRIVED';
    public const STATUS_RETURNING = 'RETURNING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const VALID_STATUSES = [
        self::STATUS_ASSIGNED, self::STATUS_READY, self::STATUS_STARTED,
        self::STATUS_ARRIVED, self::STATUS_RETURNING, self::STATUS_COMPLETED,
        self::STATUS_SUBMITTED,
    ];

    public static function find(int $id): ?array
    {
        return DB::fetch(
            'SELECT t.*, a.assignment_number, a.assignment_date, a.destination, a.purpose,
                    a.destination_latitude AS assignment_destination_latitude,
                    a.destination_longitude AS assignment_destination_longitude,
                    v.plate_number, v.vehicle_name, v.vehicle_type,
                    d.name AS driver_name, d.phone AS driver_phone
               FROM trip t
               JOIN assignments a ON a.id = t.assignment_id
               JOIN vehicles v ON v.id = t.vehicle_id
               JOIN drivers d ON d.id = t.driver_id
              WHERE t.id = ?',
            [$id]
        );
    }

    /** @return array{data:array,total:int,page:int,per_page:int,last_page:int} */
    public static function paginate(int $page = 1, int $perPage = 20, ?string $status = null, ?int $driverId = null): array
    {
        $where = ['1=1'];
        $params = [];
        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }
        if ($driverId !== null) {
            $where[] = 't.driver_id = :driver_id';
            $params['driver_id'] = $driverId;
        }
        $clause = implode(' AND ', $where);
        $total = (int)DB::scalar("SELECT COUNT(*) FROM trip t WHERE {$clause}", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $items = DB::fetchAll(
            "SELECT t.id, t.trip_number, t.assignment_id, t.vehicle_id, t.driver_id, t.status,
                    t.planned_departure_at, t.actual_started_at, t.actual_arrived_at,
                    t.actual_returning_at, t.actual_completed_at, t.actual_submitted_at,
                    a.assignment_number, a.assignment_date, a.destination, v.plate_number, v.vehicle_name,
                    d.name AS driver_name
               FROM trip t
               JOIN assignments a ON a.id = t.assignment_id
               JOIN vehicles v ON v.id = t.vehicle_id
               JOIN drivers d ON d.id = t.driver_id
              WHERE {$clause}
              ORDER BY COALESCE(t.planned_departure_at, CONCAT(a.assignment_date, ' 00:00:00')) DESC, t.id DESC
              LIMIT " . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );
        return [
            'data' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public static function events(int $tripId): array
    {
        return DB::fetchAll(
            'SELECT id, trip_id, event_type, event_uuid, occurred_at, recorded_at,
                    latitude, longitude, accuracy_m, distance_to_destination_m,
                    location_status, notes, actor_user_id, metadata_json
               FROM trip_events WHERE trip_id = ? ORDER BY id ASC',
            [$tripId]
        );
    }
}
