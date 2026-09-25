<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Assignment
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ASSIGNED,
        self::STATUS_CANCELLED,
    ];

    public static function find(int $id): ?array
    {
        return DB::fetch(
            'SELECT a.*,
                    v.vehicle_code, v.plate_number, v.vehicle_name, v.vehicle_type, v.status AS vehicle_status,
                    d.driver_code, d.name AS driver_name, d.phone AS driver_phone, d.license_expiry, d.status AS driver_status,
                    u.name AS creator_name
               FROM assignments a
               JOIN vehicles v ON v.id = a.vehicle_id
               JOIN drivers d ON d.id = a.driver_id
          LEFT JOIN users u ON u.id = a.created_by
              WHERE a.id = ?',
            [$id]
        );
    }

    public static function findByNumber(string $number): ?array
    {
        return DB::fetch('SELECT * FROM assignments WHERE assignment_number = ?', [$number]);
    }

    public static function paginate(int $page = 1, int $perPage = 15, ?string $search = null, ?string $status = null, ?string $date = null, ?int $driverId = null): array
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where[] = '(a.assignment_number LIKE :s_num OR a.destination LIKE :s_dest OR a.purpose LIKE :s_purp OR v.plate_number LIKE :s_plate OR d.name LIKE :s_drv)';
            $params['s_num'] = '%' . trim($search) . '%';
            $params['s_dest'] = '%' . trim($search) . '%';
            $params['s_purp'] = '%' . trim($search) . '%';
            $params['s_plate'] = '%' . trim($search) . '%';
            $params['s_drv'] = '%' . trim($search) . '%';
        }

        if ($status !== null && $status !== '') {
            $where[] = 'a.status = :status';
            $params['status'] = $status;
        }

        if ($date !== null && $date !== '') {
            $where[] = 'a.assignment_date = :date';
            $params['date'] = $date;
        }

        if ($driverId !== null) {
            $where[] = 'a.driver_id = :driver_id';
            $params['driver_id'] = $driverId;
        }

        $whereClause = implode(' AND ', $where);

        $totalSql = "SELECT COUNT(*) FROM assignments a
                       JOIN vehicles v ON v.id = a.vehicle_id
                       JOIN drivers d ON d.id = a.driver_id
                      WHERE {$whereClause}";
        $total = (int)DB::scalar($totalSql, $params);

        $offset = max(0, ($page - 1) * $perPage);
        $dataSql = "SELECT a.*, v.plate_number, v.vehicle_name, d.name AS driver_name, d.phone AS driver_phone
                      FROM assignments a
                      JOIN vehicles v ON v.id = a.vehicle_id
                      JOIN drivers d ON d.id = a.driver_id
                     WHERE {$whereClause}
                  ORDER BY a.assignment_date DESC, a.id DESC
                     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

        $items = DB::fetchAll($dataSql, $params);

        return [
            'data' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public static function generateNumber(string $date): string
    {
        $year = date('Y', strtotime($date));
        $prefix = "ASG-{$year}-";

        // Cari sequence terakhir untuk tahun tersebut
        $latest = DB::scalar(
            'SELECT assignment_number FROM assignments WHERE assignment_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$prefix . '%']
        );

        $seq = 1;
        if ($latest !== null && preg_match('/ASG-\d{4}-(\d{5})/', (string)$latest, $matches)) {
            $seq = ((int)$matches[1]) + 1;
        }

        return sprintf('%s%05d', $prefix, $seq);
    }

    public static function create(array $data): int
    {
        return DB::insert('assignments', [
            'assignment_number' => $data['assignment_number'],
            'assignment_date' => $data['assignment_date'],
            'vehicle_id' => (int)$data['vehicle_id'],
            'driver_id' => (int)$data['driver_id'],
            'destination' => $data['destination'],
            'purpose' => $data['purpose'],
            'passenger_count' => isset($data['passenger_count']) ? (int)$data['passenger_count'] : 1,
            'passenger_notes' => $data['passenger_notes'] ?? null,
            'st_reference' => $data['st_reference'] ?? null,
            'sppd_reference' => $data['sppd_reference'] ?? null,
            'status' => $data['status'] ?? self::STATUS_ASSIGNED,
            'notes' => $data['notes'] ?? null,
            'created_by' => !empty($data['created_by']) ? (int)$data['created_by'] : null,
        ]);
    }

    public static function update(int $id, array $data): int
    {
        $update = [];
        $allowed = [
            'assignment_date', 'vehicle_id', 'driver_id', 'destination',
            'purpose', 'passenger_count', 'passenger_notes', 'st_reference',
            'sppd_reference', 'status', 'notes'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if (in_array($field, ['vehicle_id', 'driver_id', 'passenger_count'], true)) {
                    $update[$field] = (int)$data[$field];
                } else {
                    $update[$field] = $data[$field];
                }
            }
        }

        if (empty($update)) {
            return 0;
        }

        return DB::update('assignments', $update, 'id = :id', ['id' => $id]);
    }

    public static function cancel(int $id, ?string $reason = null): int
    {
        $current = self::find($id);
        $notes = $current['notes'] ?? '';
        if ($reason !== null && $reason !== '') {
            $notes = trim($notes . "\n[CANCELLED]: " . $reason);
        }

        return DB::update('assignments', [
            'status' => self::STATUS_CANCELLED,
            'notes' => $notes,
        ], 'id = :id', ['id' => $id]);
    }
}
