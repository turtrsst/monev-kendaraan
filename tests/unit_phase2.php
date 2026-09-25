<?php

/**
 * Bootstrap test unit Phase 2 (CLI).
 * Menguji pure input & format validation:
 * - VehicleValidator
 * - DriverValidator
 * - AmbulanceValidator
 * - AssignmentValidator
 *
 * Catatan Arsitektur:
 * File ini menguji PURE UNIT VALIDATION (format, tipe data, batas karakter, status whitelist,
 * dan aturan bisnis stateless) yang terisolasi dari koneksi database runtime.
 * Pengujian validasi relasional (cek eksistensi foreign key ke database riil)
 * dilakukan pada level HTTP / integration test (tests/http_phase2.sh).
 *
 * Menjalankan: php tests/unit_phase2.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
ob_start();
require BASE_PATH . '/app/core/Autoloader.php';
require BASE_PATH . '/app/helpers/general.php';
require BASE_PATH . '/app/helpers/log.php';
App\Core\Autoloader::register();

date_default_timezone_set('Asia/Jakarta');

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
$GLOBALS['__results'] = [];

function check(string $name, bool $cond, string $detail = ''): void
{
    if ($cond) {
        $GLOBALS['__pass']++;
        $GLOBALS['__results'][] = ['PASS', $name, $detail];
        echo "[PASS] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    } else {
        $GLOBALS['__fail']++;
        $GLOBALS['__results'][] = ['FAIL', $name, $detail];
        echo "[FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

echo "=== PHASE 2 PURE UNIT TESTS (ISOLATED VALIDATORS) ===\n\n";

use App\Validators\VehicleValidator;
use App\Validators\DriverValidator;
use App\Validators\AmbulanceValidator;
use App\Validators\AssignmentValidator;

/* ---------------------------------------------------------------- */
/* 1. Vehicle Validator Tests (Pure Input & Format)                */
/* ---------------------------------------------------------------- */
$validVehicle = [
    'vehicle_code' => 'VEH-999',
    'plate_number' => 'AD 1111 ZZ',
    'vehicle_name' => 'Toyota Innova Zenix',
    'year' => '2023',
    'status' => 'ACTIVE',
    'stnk_expiry' => '2028-12-31',
];

$invalidCode = $validVehicle;
$invalidCode['vehicle_code'] = 'X'; // too short (min 3)
$errs = VehicleValidator::validate($invalidCode);
check('vehicle: code too short rejected', isset($errs['vehicle_code']), $errs['vehicle_code'] ?? '');

$invalidCodeChars = $validVehicle;
$invalidCodeChars['vehicle_code'] = 'VEH@999!'; // invalid characters
$errs = VehicleValidator::validate($invalidCodeChars);
check('vehicle: invalid code characters rejected', isset($errs['vehicle_code']), $errs['vehicle_code'] ?? '');

$invalidPlate = $validVehicle;
$invalidPlate['plate_number'] = '';
$errs = VehicleValidator::validate($invalidPlate);
check('vehicle: empty plate rejected', isset($errs['plate_number']), $errs['plate_number'] ?? '');

$invalidStatus = $validVehicle;
$invalidStatus['status'] = 'INVALID_STATUS';
$errs = VehicleValidator::validate($invalidStatus);
check('vehicle: invalid status rejected', isset($errs['status']), $errs['status'] ?? '');

$invalidStnk = $validVehicle;
$invalidStnk['stnk_expiry'] = 'invalid-date';
$errs = VehicleValidator::validate($invalidStnk);
check('vehicle: invalid stnk date format rejected', isset($errs['stnk_expiry']), $errs['stnk_expiry'] ?? '');

$invalidYear = $validVehicle;
$invalidYear['year'] = '1950'; // out of range (< 1980)
$errs = VehicleValidator::validate($invalidYear);
check('vehicle: out of range year rejected', isset($errs['year']), $errs['year'] ?? '');

/* ---------------------------------------------------------------- */
/* 2. Driver Validator Tests (Pure Input & Format)                 */
/* ---------------------------------------------------------------- */
$validDriver = [
    'driver_code' => 'DRV-999',
    'name' => 'Bambang Tri',
    'phone' => '081299887766',
    'license_type' => 'SIM B1 UMUM',
    'license_number' => '123456789012',
    'license_expiry' => '2028-10-10',
    'status' => 'ACTIVE',
];

$invalidDrvCode = $validDriver;
$invalidDrvCode['driver_code'] = '';
$errs = DriverValidator::validate($invalidDrvCode);
check('driver: empty driver code rejected', isset($errs['driver_code']), $errs['driver_code'] ?? '');

$invalidPhone = $validDriver;
$invalidPhone['phone'] = '123'; // too short (min 8)
$errs = DriverValidator::validate($invalidPhone);
check('driver: short phone rejected', isset($errs['phone']), $errs['phone'] ?? '');

$invalidLicExp = $validDriver;
$invalidLicExp['license_expiry'] = '31-12-2025'; // wrong format (must Y-m-d)
$errs = DriverValidator::validate($invalidLicExp);
check('driver: invalid license expiry date format rejected', isset($errs['license_expiry']), $errs['license_expiry'] ?? '');

$invalidDrvStatus = $validDriver;
$invalidDrvStatus['status'] = 'FIRED';
$errs = DriverValidator::validate($invalidDrvStatus);
check('driver: invalid status rejected', isset($errs['status']), $errs['status'] ?? '');

/* ---------------------------------------------------------------- */
/* 3. Ambulance Validator Tests (Pure Input & Format)              */
/* ---------------------------------------------------------------- */
$validAmbulance = [
    'vehicle_id' => 1,
    'ambulance_code' => 'AMB-99',
    'ambulance_name' => 'Ambulans Reaksi Cepat',
    'ambulance_type' => 'ICU_ADVANCE',
    'base_location' => 'IGD RSUP Klaten',
    'readiness' => 'READY',
];

$invalidAmbCode = $validAmbulance;
$invalidAmbCode['ambulance_code'] = '';
$errs = AmbulanceValidator::validate($invalidAmbCode, 1, false);
check('ambulance: empty code rejected', isset($errs['ambulance_code']), $errs['ambulance_code'] ?? '');

$invalidReadiness = $validAmbulance;
$invalidReadiness['readiness'] = 'NOT_A_STATUS';
$errs = AmbulanceValidator::validate($invalidReadiness, 1, false);
check('ambulance: invalid readiness rejected', isset($errs['readiness']), $errs['readiness'] ?? '');

$invalidAmbDates = $validAmbulance;
$invalidAmbDates['insurance_expiry'] = 'invalid-date';
$errs = AmbulanceValidator::validate($invalidAmbDates, 1, false);
check('ambulance: invalid insurance date rejected', isset($errs['insurance_expiry']), $errs['insurance_expiry'] ?? '');

$createWithoutVehicle = $validAmbulance;
$createWithoutVehicle['vehicle_id'] = 0;
$errs = AmbulanceValidator::validate($createWithoutVehicle, null, true);
check('ambulance: create without vehicle rejected', isset($errs['vehicle_id']), $errs['vehicle_id'] ?? '');

/* ---------------------------------------------------------------- */
/* 4. Assignment Validator Tests (Pure Input & Format)             */
/* ---------------------------------------------------------------- */
$baseAssignment = [
    'assignment_date' => '2026-10-01',
    'vehicle_id' => 0, // diuji khusus untuk input format kendaraan
    'driver_id' => 0,  // diuji khusus untuk input format driver
    'destination' => 'RSUD dr. Moewardi Surakarta',
    'purpose' => 'Rujukan pasien emergency',
    'passenger_count' => 2,
    'status' => 'ASSIGNED',
];

// 4.1 Missing/Invalid Vehicle ID
$missingVehicle = $baseAssignment;
$missingVehicle['vehicle_id'] = 0;
$errs = AssignmentValidator::validate($missingVehicle);
check('assignment: missing vehicle_id rejected', isset($errs['vehicle_id']), $errs['vehicle_id'] ?? '');

// 4.2 Missing/Invalid Driver ID
$missingDriver = $baseAssignment;
$missingDriver['driver_id'] = 0;
$errs = AssignmentValidator::validate($missingDriver);
check('assignment: missing driver_id rejected', isset($errs['driver_id']), $errs['driver_id'] ?? '');

// 4.3 Invalid Date Format
$invalidDateAsg = $baseAssignment;
$invalidDateAsg['assignment_date'] = 'invalid-date';
$errs = AssignmentValidator::validate($invalidDateAsg);
check('assignment: invalid date format rejected', isset($errs['assignment_date']), $errs['assignment_date'] ?? '');

// 4.4 Empty Assignment Date
$emptyDateAsg = $baseAssignment;
$emptyDateAsg['assignment_date'] = '';
$errs = AssignmentValidator::validate($emptyDateAsg);
check('assignment: empty date rejected', isset($errs['assignment_date']), $errs['assignment_date'] ?? '');

// 4.5 Empty Destination
$emptyDest = $baseAssignment;
$emptyDest['destination'] = '';
$errs = AssignmentValidator::validate($emptyDest);
check('assignment: empty destination rejected', isset($errs['destination']), $errs['destination'] ?? '');

// 4.6 Empty Purpose
$emptyPurpose = $baseAssignment;
$emptyPurpose['purpose'] = '';
$errs = AssignmentValidator::validate($emptyPurpose);
check('assignment: empty purpose rejected', isset($errs['purpose']), $errs['purpose'] ?? '');

// 4.7 Invalid Status (Whitelist Violation / Phase 3 Status Forbidden)
$invalidStatusAsg = $baseAssignment;
$invalidStatusAsg['status'] = 'COMPLETED'; // Only DRAFT, ASSIGNED, CANCELLED allowed in Phase 2
$errs = AssignmentValidator::validate($invalidStatusAsg);
check('assignment: invalid status rejected (Phase 3 trip statuses forbidden)', isset($errs['status']), $errs['status'] ?? '');

// 4.8 Out of Range Passenger Count
$invalidPassengers = $baseAssignment;
$invalidPassengers['passenger_count'] = 150; // max 100
$errs = AssignmentValidator::validate($invalidPassengers);
check('assignment: excessive passenger count rejected', isset($errs['passenger_count']), $errs['passenger_count'] ?? '');

/* ---------------------------------------------------------------- */
/* Ringkasan                                                       */
/* ---------------------------------------------------------------- */
echo "\n----------------------------------------\n";
echo "RINGKASAN UNIT TEST PHASE 2 (PURE VALIDATORS):\n";
echo "PASS: {$GLOBALS['__pass']}\n";
echo "FAIL: {$GLOBALS['__fail']}\n";
echo "----------------------------------------\n";

exit($GLOBALS['__fail'] > 0 ? 1 : 0);
