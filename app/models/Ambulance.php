<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Ambulance
{
    public const READINESS_READY = 'READY';
    public const READINESS_STANDBY = 'STANDBY';
    public const READINESS_MAINTENANCE = 'MAINTENANCE';
    public const READINESS_UNAVAILABLE = 'UNAVAILABLE';

    public const VALID_READINESS = [
        self::READINESS_READY,
        self::READINESS_STANDBY,
        self::READINESS_MAINTENANCE,
        self::READINESS_UNAVAILABLE,
    ];

    public static function find(int $vehicleId): ?array
    {
        return DB::fetch(
            'SELECT a.*, v.plate_number, v.vehicle_name, v.vehicle_type, v.status AS vehicle_status, v.ownership
               FROM ambulance_details a
               JOIN vehicles v ON v.id = a.vehicle_id
              WHERE a.vehicle_id = ?',
            [$vehicleId]
        );
    }

    public static function findByCode(string $code): ?array
    {
        return DB::fetch('SELECT * FROM ambulance_details WHERE ambulance_code = ?', [$code]);
    }

    public static function paginate(int $page = 1, int $perPage = 15, ?string $search = null, ?string $readiness = null): array
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where[] = '(a.ambulance_code LIKE :s_code OR a.ambulance_name LIKE :s_name OR v.plate_number LIKE :s_plate OR a.base_location LIKE :s_loc)';
            $params['s_code'] = '%' . trim($search) . '%';
            $params['s_name'] = '%' . trim($search) . '%';
            $params['s_plate'] = '%' . trim($search) . '%';
            $params['s_loc'] = '%' . trim($search) . '%';
        }

        if ($readiness !== null && $readiness !== '') {
            $where[] = 'a.readiness = :readiness';
            $params['readiness'] = $readiness;
        }

        $whereClause = implode(' AND ', $where);

        $totalSql = "SELECT COUNT(*) FROM ambulance_details a JOIN vehicles v ON v.id = a.vehicle_id WHERE {$whereClause}";
        $total = (int)DB::scalar($totalSql, $params);

        $offset = max(0, ($page - 1) * $perPage);
        $dataSql = "SELECT a.*, v.plate_number, v.vehicle_name, v.status AS vehicle_status
                      FROM ambulance_details a
                      JOIN vehicles v ON v.id = a.vehicle_id
                     WHERE {$whereClause}
                  ORDER BY a.ambulance_code ASC
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

    public static function create(array $data): void
    {
        DB::insert('ambulance_details', [
            'vehicle_id' => (int)$data['vehicle_id'],
            'ambulance_code' => $data['ambulance_code'],
            'ambulance_name' => $data['ambulance_name'],
            'ambulance_type' => $data['ambulance_type'] ?? 'TRANSPORT',
            'base_location' => $data['base_location'] ?? 'Pool Ambulans RS',
            'readiness' => $data['readiness'] ?? self::READINESS_READY,
            'chassis_number' => $data['chassis_number'] ?? null,
            'engine_number' => $data['engine_number'] ?? null,
            'stnk_expiry' => !empty($data['stnk_expiry']) ? $data['stnk_expiry'] : null,
            'kir_expiry' => !empty($data['kir_expiry']) ? $data['kir_expiry'] : null,
            'insurance_expiry' => !empty($data['insurance_expiry']) ? $data['insurance_expiry'] : null,
            'fuel_level' => $data['fuel_level'] ?? 'FULL',
            'equipment_notes' => $data['equipment_notes'] ?? null,
            'last_check_at' => !empty($data['last_check_at']) ? $data['last_check_at'] : null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public static function update(int $vehicleId, array $data): int
    {
        $update = [];
        $allowed = [
            'ambulance_code', 'ambulance_name', 'ambulance_type', 'base_location',
            'readiness', 'chassis_number', 'engine_number', 'stnk_expiry',
            'kir_expiry', 'insurance_expiry', 'fuel_level', 'equipment_notes',
            'last_check_at', 'notes'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if (in_array($field, ['stnk_expiry', 'kir_expiry', 'insurance_expiry', 'last_check_at'], true) && empty($data[$field])) {
                    $update[$field] = null;
                } else {
                    $update[$field] = $data[$field];
                }
            }
        }

        if (empty($update)) {
            return 0;
        }

        return DB::update('ambulance_details', $update, 'vehicle_id = :vid', ['vid' => $vehicleId]);
    }

    public static function delete(int $vehicleId): int
    {
        $st = DB::run('DELETE FROM ambulance_details WHERE vehicle_id = ?', [$vehicleId]);
        return $st->rowCount();
    }
}
