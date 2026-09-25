<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Vehicle
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_MAINTENANCE = 'MAINTENANCE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_RETIRED = 'RETIRED';

    public const VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_MAINTENANCE,
        self::STATUS_INACTIVE,
        self::STATUS_RETIRED,
    ];

    public static function find(int $id): ?array
    {
        return DB::fetch(
            'SELECT v.*, a.ambulance_code, a.ambulance_name, a.ambulance_type, a.readiness
               FROM vehicles v
          LEFT JOIN ambulance_details a ON a.vehicle_id = v.id
              WHERE v.id = ?',
            [$id]
        );
    }

    public static function findByCode(string $code): ?array
    {
        return DB::fetch('SELECT * FROM vehicles WHERE vehicle_code = ?', [$code]);
    }

    public static function findByPlate(string $plate): ?array
    {
        return DB::fetch('SELECT * FROM vehicles WHERE plate_number = ?', [$plate]);
    }

    public static function paginate(int $page = 1, int $perPage = 15, ?string $search = null, ?string $status = null, ?string $type = null): array
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where[] = '(v.vehicle_code LIKE :search_code OR v.plate_number LIKE :search_plate OR v.vehicle_name LIKE :search_name)';
            $params['search_code'] = '%' . trim($search) . '%';
            $params['search_plate'] = '%' . trim($search) . '%';
            $params['search_name'] = '%' . trim($search) . '%';
        }

        if ($status !== null && $status !== '') {
            $where[] = 'v.status = :status';
            $params['status'] = $status;
        }

        if ($type !== null && $type !== '') {
            $where[] = 'v.vehicle_type = :type';
            $params['type'] = $type;
        }

        $whereClause = implode(' AND ', $where);

        $totalSql = "SELECT COUNT(*) FROM vehicles v WHERE {$whereClause}";
        $total = (int)DB::scalar($totalSql, $params);

        $offset = max(0, ($page - 1) * $perPage);
        $dataSql = "SELECT v.*, a.ambulance_code, a.readiness AS ambulance_readiness
                      FROM vehicles v
                 LEFT JOIN ambulance_details a ON a.vehicle_id = v.id
                     WHERE {$whereClause}
                  ORDER BY v.id DESC
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

    public static function listActive(?string $forDate = null): array
    {
        return DB::fetchAll(
            'SELECT v.id, v.vehicle_code, v.plate_number, v.vehicle_name, v.vehicle_type, v.status,
                    a.ambulance_code, a.readiness AS ambulance_readiness
               FROM vehicles v
          LEFT JOIN ambulance_details a ON a.vehicle_id = v.id
              WHERE v.status = ?
           ORDER BY v.vehicle_name ASC',
            [self::STATUS_ACTIVE]
        );
    }

    public static function create(array $data): int
    {
        return DB::insert('vehicles', [
            'vehicle_code' => $data['vehicle_code'],
            'plate_number' => $data['plate_number'],
            'vehicle_name' => $data['vehicle_name'],
            'vehicle_type' => $data['vehicle_type'] ?? 'OPERASIONAL',
            'ownership' => $data['ownership'] ?? 'DINAS',
            'year' => isset($data['year']) && $data['year'] !== '' ? (int)$data['year'] : null,
            'stnk_expiry' => !empty($data['stnk_expiry']) ? $data['stnk_expiry'] : null,
            'kir_expiry' => !empty($data['kir_expiry']) ? $data['kir_expiry'] : null,
            'status' => $data['status'] ?? self::STATUS_ACTIVE,
            'current_odometer' => isset($data['current_odometer']) ? (int)$data['current_odometer'] : 0,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public static function update(int $id, array $data): int
    {
        $update = [];
        $allowed = [
            'vehicle_code', 'plate_number', 'vehicle_name', 'vehicle_type',
            'ownership', 'year', 'stnk_expiry', 'kir_expiry', 'status',
            'current_odometer', 'notes'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if (($field === 'stnk_expiry' || $field === 'kir_expiry') && empty($data[$field])) {
                    $update[$field] = null;
                } elseif ($field === 'year') {
                    $update[$field] = $data[$field] !== '' && $data[$field] !== null ? (int)$data[$field] : null;
                } elseif ($field === 'current_odometer') {
                    $update[$field] = (int)$data[$field];
                } else {
                    $update[$field] = $data[$field];
                }
            }
        }

        if (empty($update)) {
            return 0;
        }

        return DB::update('vehicles', $update, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        $st = DB::run('DELETE FROM vehicles WHERE id = ?', [$id]);
        return $st->rowCount();
    }
}
