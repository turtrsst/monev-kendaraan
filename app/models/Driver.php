<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Driver
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';

    public const VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_SUSPENDED,
    ];

    public static function find(int $id): ?array
    {
        return DB::fetch(
            'SELECT d.*, u.username, u.name AS user_fullname, u.email AS user_email
               FROM drivers d
          LEFT JOIN users u ON u.id = d.user_id
              WHERE d.id = ?',
            [$id]
        );
    }

    public static function findByCode(string $code): ?array
    {
        return DB::fetch('SELECT * FROM drivers WHERE driver_code = ?', [$code]);
    }

    public static function findByUserId(int $userId): ?array
    {
        return DB::fetch('SELECT * FROM drivers WHERE user_id = ?', [$userId]);
    }

    public static function paginate(int $page = 1, int $perPage = 15, ?string $search = null, ?string $status = null): array
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where[] = '(d.driver_code LIKE :search_code OR d.name LIKE :search_name OR d.phone LIKE :search_phone OR d.license_number LIKE :search_lic)';
            $params['search_code'] = '%' . trim($search) . '%';
            $params['search_name'] = '%' . trim($search) . '%';
            $params['search_phone'] = '%' . trim($search) . '%';
            $params['search_lic'] = '%' . trim($search) . '%';
        }

        if ($status !== null && $status !== '') {
            $where[] = 'd.status = :status';
            $params['status'] = $status;
        }

        $whereClause = implode(' AND ', $where);

        $totalSql = "SELECT COUNT(*) FROM drivers d WHERE {$whereClause}";
        $total = (int)DB::scalar($totalSql, $params);

        $offset = max(0, ($page - 1) * $perPage);
        $dataSql = "SELECT d.*, u.username
                      FROM drivers d
                 LEFT JOIN users u ON u.id = d.user_id
                     WHERE {$whereClause}
                  ORDER BY d.id DESC
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

    public static function listActive(): array
    {
        return DB::fetchAll(
            'SELECT d.id, d.driver_code, d.name, d.phone, d.license_type, d.license_number, d.license_expiry, d.status
               FROM drivers d
              WHERE d.status = ?
           ORDER BY d.name ASC',
            [self::STATUS_ACTIVE]
        );
    }

    public static function create(array $data): int
    {
        return DB::insert('drivers', [
            'user_id' => !empty($data['user_id']) ? (int)$data['user_id'] : null,
            'driver_code' => $data['driver_code'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'license_type' => $data['license_type'] ?? 'SIM A',
            'license_number' => $data['license_number'],
            'license_expiry' => $data['license_expiry'],
            'status' => $data['status'] ?? self::STATUS_ACTIVE,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public static function update(int $id, array $data): int
    {
        $update = [];
        $allowed = [
            'user_id', 'driver_code', 'name', 'phone', 'license_type',
            'license_number', 'license_expiry', 'status', 'notes'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'user_id') {
                    $update[$field] = !empty($data[$field]) ? (int)$data[$field] : null;
                } else {
                    $update[$field] = $data[$field];
                }
            }
        }

        if (empty($update)) {
            return 0;
        }

        return DB::update('drivers', $update, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        $st = DB::run('DELETE FROM drivers WHERE id = ?', [$id]);
        return $st->rowCount();
    }
}
