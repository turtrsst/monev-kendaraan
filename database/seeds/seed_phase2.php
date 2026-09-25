<?php

declare(strict_types=1);

/**
 * Seed data master Phase 2 untuk testing dan development.
 * Kendaraan, Driver, Ambulance Profile, Assignment.
 */

namespace Database\Seeds;

use App\Core\DB;

final class Phase2Seeder
{
    public static function run(): void
    {
        // 1. Vehicles
        $vehicles = [
            [
                'vehicle_code' => 'VEH-001',
                'plate_number' => 'AD 1234 AB',
                'vehicle_name' => 'Toyota Avanza 1.3 G',
                'vehicle_type' => 'OPERASIONAL',
                'ownership' => 'DINAS',
                'year' => 2021,
                'stnk_expiry' => '2027-05-15',
                'kir_expiry' => null,
                'status' => 'ACTIVE',
                'current_odometer' => 35200,
                'notes' => 'Kendaraan operasional direksi/staf',
            ],
            [
                'vehicle_code' => 'VEH-002',
                'plate_number' => 'AD 5678 CD',
                'vehicle_name' => 'Toyota HiAce Premio Ambulans',
                'vehicle_type' => 'AMBULANCE',
                'ownership' => 'DINAS',
                'year' => 2022,
                'stnk_expiry' => '2028-08-20',
                'kir_expiry' => '2027-02-20',
                'status' => 'ACTIVE',
                'current_odometer' => 18450,
                'notes' => 'Unit ambulans gawat darurat ICU',
            ],
            [
                'vehicle_code' => 'VEH-003',
                'plate_number' => 'AD 9012 EF',
                'vehicle_name' => 'Daihatsu Gran Max Ambulans Transport',
                'vehicle_type' => 'AMBULANCE',
                'ownership' => 'DINAS',
                'year' => 2019,
                'stnk_expiry' => '2025-11-10',
                'kir_expiry' => '2025-05-10',
                'status' => 'ACTIVE',
                'current_odometer' => 89100,
                'notes' => 'Unit ambulans jenazah/transport',
            ],
            [
                'vehicle_code' => 'VEH-004',
                'plate_number' => 'AD 3456 GH',
                'vehicle_name' => 'Mitsubishi L300 Operasional Logistik',
                'vehicle_type' => 'LOGISTIK',
                'ownership' => 'DINAS',
                'year' => 2018,
                'stnk_expiry' => '2026-03-01',
                'kir_expiry' => '2026-09-01',
                'status' => 'MAINTENANCE',
                'current_odometer' => 142000,
                'notes' => 'Sedang perbaikan transmisi',
            ],
            [
                'vehicle_code' => 'VEH-005',
                'plate_number' => 'AD 7890 IJ',
                'vehicle_name' => 'Toyota Kijang Kapsul (Purna Tugas)',
                'vehicle_type' => 'OPERASIONAL',
                'ownership' => 'DINAS',
                'year' => 2002,
                'stnk_expiry' => '2022-01-01',
                'kir_expiry' => null,
                'status' => 'RETIRED',
                'current_odometer' => 320000,
                'notes' => 'Sudah tidak beroperasi',
            ],
        ];

        foreach ($vehicles as $v) {
            $existing = DB::fetch('SELECT id FROM vehicles WHERE vehicle_code = ?', [$v['vehicle_code']]);
            if ($existing === null) {
                DB::insert('vehicles', $v);
            }
        }

        // 2. Drivers
        $drivers = [
            [
                'driver_code' => 'DRV-001',
                'name' => 'Budi Santoso',
                'phone' => '081234567890',
                'license_type' => 'SIM B1 UMUM',
                'license_number' => '901234567891',
                'license_expiry' => '2028-12-31',
                'status' => 'ACTIVE',
                'notes' => 'Sopir ambulans gawat darurat sertifikasi BTCLS',
            ],
            [
                'driver_code' => 'DRV-002',
                'name' => 'Agus Setiawan',
                'phone' => '082134567891',
                'license_type' => 'SIM A',
                'license_number' => '901234567892',
                'license_expiry' => '2027-06-30',
                'status' => 'ACTIVE',
                'notes' => 'Sopir kendaraan dinas operasional umum',
            ],
            [
                'driver_code' => 'DRV-003',
                'name' => 'Siti Rahmawati (Nonaktif)',
                'phone' => '083134567892',
                'license_type' => 'SIM A',
                'license_number' => '901234567893',
                'license_expiry' => '2027-01-15',
                'status' => 'INACTIVE',
                'notes' => 'Cuti di luar tanggungan negara',
            ],
            [
                'driver_code' => 'DRV-004',
                'name' => 'Joko Widodo (Suspended)',
                'phone' => '084134567893',
                'license_type' => 'SIM A',
                'license_number' => '901234567894',
                'license_expiry' => '2026-10-01',
                'status' => 'SUSPENDED',
                'notes' => 'Masa skorsing pelanggaran disiplin',
            ],
            [
                'driver_code' => 'DRV-005',
                'name' => 'Hendra Kusuma (SIM Expired)',
                'phone' => '085134567894',
                'license_type' => 'SIM A',
                'license_number' => '901234567895',
                'license_expiry' => '2023-01-01',
                'status' => 'ACTIVE',
                'notes' => 'SIM mati, menunggu perpanjangan',
            ],
        ];

        foreach ($drivers as $d) {
            $existing = DB::fetch('SELECT id FROM drivers WHERE driver_code = ?', [$d['driver_code']]);
            if ($existing === null) {
                DB::insert('drivers', $d);
            }
        }

        // 3. Ambulance details for VEH-002 and VEH-003
        $veh2 = DB::fetch('SELECT id FROM vehicles WHERE vehicle_code = ?', ['VEH-002']);
        if ($veh2 !== null) {
            $amb = DB::fetch('SELECT vehicle_id FROM ambulance_details WHERE vehicle_id = ?', [$veh2['id']]);
            if ($amb === null) {
                DB::insert('ambulance_details', [
                    'vehicle_id' => (int)$veh2['id'],
                    'ambulance_code' => 'AMB-01',
                    'ambulance_name' => 'Ambulans AGD Advance ICU',
                    'ambulance_type' => 'ICU_ADVANCE',
                    'base_location' => 'IGD RSUP dr. Soeradji Tirtonegoro',
                    'readiness' => 'READY',
                    'chassis_number' => 'MHF11KL2022001',
                    'engine_number' => '1GD998877',
                    'stnk_expiry' => '2028-08-20',
                    'kir_expiry' => '2027-02-20',
                    'insurance_expiry' => '2027-08-20',
                    'fuel_level' => 'FULL',
                    'equipment_notes' => 'Ventilator transport, Defibrillator, Syringe pump, Oksigen central 2x6m3',
                    'last_check_at' => date('Y-m-d H:i:s'),
                    'notes' => 'Siap rujukan luar kota',
                ]);
            }
        }

        $veh3 = DB::fetch('SELECT id FROM vehicles WHERE vehicle_code = ?', ['VEH-003']);
        if ($veh3 !== null) {
            $amb = DB::fetch('SELECT vehicle_id FROM ambulance_details WHERE vehicle_id = ?', [$veh3['id']]);
            if ($amb === null) {
                DB::insert('ambulance_details', [
                    'vehicle_id' => (int)$veh3['id'],
                    'ambulance_code' => 'AMB-02',
                    'ambulance_name' => 'Ambulans Jenazah & Transport Standar',
                    'ambulance_type' => 'TRANSPORT',
                    'base_location' => 'Pool Ambulans RSUP Klaten',
                    'readiness' => 'STANDBY',
                    'chassis_number' => 'MHF22KL2019002',
                    'engine_number' => '3SZ112233',
                    'stnk_expiry' => '2025-11-10',
                    'kir_expiry' => '2025-05-10',
                    'insurance_expiry' => '2026-11-10',
                    'fuel_level' => '3/4',
                    'equipment_notes' => 'Brankar stretcher standar, Tabung oksigen transport 1m3',
                    'last_check_at' => date('Y-m-d H:i:s'),
                    'notes' => 'Standby rujukan rutin / jemput pasien',
                ]);
            }
        }

        // 4. Assignments
        $veh1 = DB::fetch('SELECT id FROM vehicles WHERE vehicle_code = ?', ['VEH-001']);
        $drv2 = DB::fetch('SELECT id FROM drivers WHERE driver_code = ?', ['DRV-002']);
        if ($veh1 !== null && $drv2 !== null) {
            $asg = DB::fetch('SELECT id FROM assignments WHERE assignment_number = ?', ['ASG-2026-00001']);
            if ($asg === null) {
                DB::insert('assignments', [
                    'assignment_number' => 'ASG-2026-00001',
                    'assignment_date' => date('Y-m-d'),
                    'vehicle_id' => (int)$veh1['id'],
                    'driver_id' => (int)$drv2['id'],
                    'destination' => 'Dinas Kesehatan Provinsi Jawa Tengah, Semarang',
                    'purpose' => 'Koordinasi evaluasi program rujukan regional Jawa Tengah',
                    'passenger_count' => 3,
                    'passenger_notes' => 'Direktur Medik, Ka. Subbag Keuangan, Ka. Seksi Rujukan',
                    'st_reference' => 'ST/089/TU.02/RSST/2026',
                    'sppd_reference' => 'SPPD/089/2026',
                    'status' => 'ASSIGNED',
                    'notes' => 'Berangkat pukul 07.00 WIB dari pool RS',
                    'created_by' => null,
                ]);
            }
        }
    }
}
