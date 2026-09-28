<?php
/**
 * Phase 3 integration tests. Requires migrated kendaraan_logbook and PHP 8.2+ with pdo_mysql.
 * Run only against an isolated test database; creates and removes uniquely prefixed fixtures.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/core/Autoloader.php';
require BASE_PATH . '/app/helpers/log.php';
App\Core\Autoloader::register();
App\Core\Env::load(BASE_PATH . '/.env');

use App\Core\DB;
use App\Core\HttpException;
use App\Services\TripService;

$arguments = $argv ?? [];
if (($arguments[1] ?? '') === '--race-worker') {
    [, , $tripId, $userId, $uuid, $readyFile] = $arguments;
    $deadline = microtime(true) + 10;
    while (!is_file($readyFile) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (!is_file($readyFile)) {
        fwrite(STDERR, "race barrier timeout\n");
        exit(5);
    }
    try {
        TripService::start((int)$tripId, (string)$uuid, ['id' => (int)$userId, 'role' => 'driver']);
        echo "SUCCESS\n";
        exit(0);
    } catch (HttpException $e) {
        if ($e->status === 409) {
            echo "CONFLICT\n";
            exit(3);
        }
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(4);
    }
}

$passed = 0;
$failed = 0;
$test = static function (string $name, bool $condition) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "[PASS] {$name}\n";
    } else {
        $failed++;
        echo "[FAIL] {$name}\n";
    }
};
$expectStatus = static function (callable $operation, int $status): bool {
    try {
        $operation();
        return false;
    } catch (HttpException $e) {
        return $e->status === $status;
    }
};

$pdo = DB::pdo();
$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($dbName !== 'kendaraan_logbook' || getenv('PHASE3_TEST_DB_OK') !== '1') {
    fwrite(STDERR, "Refusing to mutate {$dbName}. Configure an isolated database named kendaraan_logbook and explicitly set PHASE3_TEST_DB_OK=1.\n");
    exit(2);
}
$token = strtoupper(bin2hex(random_bytes(4)));
$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
$ids = ['users' => [], 'vehicles' => [], 'drivers' => [], 'assignments' => [], 'trips' => []];
$userIds = [];
$tripIds = [];
$goFile = sys_get_temp_dir() . '/phase3-race-' . strtolower($token);

try {
    $roleStmt = $pdo->prepare('SELECT slug, id FROM roles WHERE slug IN (\'admin\',\'operator\',\'driver\',\'pimpinan\')');
    $roleStmt->execute();
    $roles = $roleStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!isset($roles['admin'], $roles['operator'], $roles['driver'], $roles['pimpinan'])) {
        throw new RuntimeException('Required seeded roles admin and driver were not found.');
    }

    $addUser = static function (string $role, string $name) use ($pdo, $roles, $token, &$ids, &$userIds): int {
        $statement = $pdo->prepare('INSERT INTO users (username, password_hash, name, role_id, is_active) VALUES (?, ?, ?, ?, 1)');
        $statement->execute(['p3_' . strtolower($token) . '_' . strtolower($name), password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT), 'Phase3 ' . $name, (int)$roles[$role]]);
        $id = (int)$pdo->lastInsertId();
        $ids['users'][] = $id;
        $userIds[$name] = $id;
        return $id;
    };
    $adminId = $addUser('admin', 'admin');
    $operatorId = $addUser('operator', 'operator');
    $monitorId = $addUser('pimpinan', 'monitor');
    $driverAId = $addUser('driver', 'drivera');
    $driverBId = $addUser('driver', 'driverb');
    $admin = ['id' => $adminId, 'role' => 'admin'];
    $operator = ['id' => $operatorId, 'role' => 'operator'];
    $monitor = ['id' => $monitorId, 'role' => 'pimpinan'];
    $driverA = ['id' => $driverAId, 'role' => 'driver'];
    $driverB = ['id' => $driverBId, 'role' => 'driver'];

    $addVehicle = static function (string $suffix, string $status) use ($pdo, $token, &$ids): int {
        $statement = $pdo->prepare('INSERT INTO vehicles (vehicle_code, plate_number, vehicle_name, status) VALUES (?, ?, ?, ?)');
        $statement->execute(['P3-' . $token . '-' . $suffix, 'P3' . $token . $suffix, 'Phase3 fixture ' . $suffix, $status]);
        $id = (int)$pdo->lastInsertId();
        $ids['vehicles'][] = $id;
        return $id;
    };
    $activeVehicle = $addVehicle('A', 'ACTIVE');
    $inactiveVehicle = $addVehicle('X', 'INACTIVE');

    $addDriver = static function (int $userId, string $suffix, string $status) use ($pdo, $token, &$ids): int {
        $statement = $pdo->prepare('INSERT INTO drivers (user_id, driver_code, name, phone, license_type, license_number, license_expiry, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$userId, 'P3-' . $token . '-' . $suffix, 'Phase3 ' . $suffix, '081234567890', 'SIM A', 'P3' . $token . $suffix, '2099-12-31', $status]);
        $id = (int)$pdo->lastInsertId();
        $ids['drivers'][] = $id;
        return $id;
    };
    $activeDriverA = $addDriver($driverAId, 'A', 'ACTIVE');
    $activeDriverB = $addDriver($driverBId, 'B', 'ACTIVE');
    $inactiveDriver = $addDriver($addUser('driver', 'inactive'), 'X', 'INACTIVE');

    $addAssignment = static function (string $suffix, int $vehicleId, int $driverId, string $status = 'ASSIGNED') use ($pdo, $token, $today, $adminId, &$ids): int {
        $statement = $pdo->prepare('INSERT INTO assignments (assignment_number, assignment_date, vehicle_id, driver_id, destination, destination_latitude, destination_longitude, purpose, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute(['P3-' . $token . '-' . $suffix, $today, $vehicleId, $driverId, 'Tujuan fixture Phase 3', -7.2, 110.0, 'Uji integrasi perjalanan Phase 3', $status, $adminId]);
        $id = (int)$pdo->lastInsertId();
        $ids['assignments'][] = $id;
        return $id;
    };

    $validAssignment = $addAssignment('A', $activeVehicle, $activeDriverA);
    $cancelledAssignment = $addAssignment('C', $activeVehicle, $activeDriverA, 'CANCELLED');
    $inactiveVehicleAssignment = $addAssignment('V', $inactiveVehicle, $activeDriverA);
    $inactiveDriverAssignment = $addAssignment('D', $activeVehicle, $inactiveDriver);
    $driverBAssignment = $addAssignment('B', $activeVehicle, $activeDriverB);
    $raceAssignment = $addAssignment('R', $activeVehicle, $activeDriverA);

    $createUuid = 'c6000000-0000-4000-8000-' . str_pad((string)hexdec(substr($token, 0, 6)), 12, '0', STR_PAD_LEFT);
    $created = TripService::create($validAssignment, $createUuid, $admin);
    $mainTripId = (int)$created['trip']['id'];
    $tripIds[] = $mainTripId;
    $ids['trips'][] = $mainTripId;
    $test('valid ASSIGNED assignment creates trip and snapshots vehicle/driver',
        $created['trip']['status'] === 'ASSIGNED'
        && (int)$created['trip']['vehicle_id'] === $activeVehicle
        && (int)$created['trip']['driver_id'] === $activeDriverA
        && preg_match('/^TRP-\d{4}-\d{5}$/', $created['trip']['trip_number']) === 1);
    $test('driver cannot create a trip', $expectStatus(fn() => TripService::create($driverBAssignment, 'c6000024-0000-4000-8000-000000000024', $driverA), 403));
    $replayedCreate = TripService::create($validAssignment, $createUuid, $admin);
    $test('repeated create UUID returns same trip without duplicate', $replayedCreate['idempotent'] && (int)$replayedCreate['trip']['id'] === $mainTripId);
    $test('missing assignment rejected', $expectStatus(fn() => TripService::create(429496729, 'c6000001-0000-4000-8000-000000000001', $admin), 404));
    $test('cancelled assignment rejected', $expectStatus(fn() => TripService::create($cancelledAssignment, 'c6000002-0000-4000-8000-000000000002', $admin), 422));
    $test('inactive vehicle rejected', $expectStatus(fn() => TripService::create($inactiveVehicleAssignment, 'c6000003-0000-4000-8000-000000000003', $admin), 422));
    $test('inactive driver rejected', $expectStatus(fn() => TripService::create($inactiveDriverAssignment, 'c6000004-0000-4000-8000-000000000004', $admin), 422));
    $test('second trip for same assignment rejected', $expectStatus(fn() => TripService::create($validAssignment, 'c6000005-0000-4000-8000-000000000005', $admin), 409));
    $test('create UUID reused with different payload rejected', $expectStatus(fn() => TripService::create($driverBAssignment, $createUuid, $admin), 409));
    $operatorTrip = TripService::create($driverBAssignment, 'c6000025-0000-4000-8000-000000000025', $operator);
    $tripIds[] = (int)$operatorTrip['trip']['id'];
    $ids['trips'][] = (int)$operatorTrip['trip']['id'];
    $test('operator is authorized to create a trip', $operatorTrip['trip']['status'] === 'ASSIGNED');
    $test('monitor role can read but cannot mutate trip',
        TripService::findAuthorized($mainTripId, $monitor)['status'] === 'ASSIGNED'
        && $expectStatus(fn() => TripService::ready($mainTripId, 'c6000026-0000-4000-8000-000000000026', $monitor), 403));

    $test('driver B cannot read driver A trip (IDOR)', $expectStatus(fn() => TripService::findAuthorized($mainTripId, $driverB), 404));
    $test('driver B cannot execute driver A trip (IDOR)', $expectStatus(fn() => TripService::start($mainTripId, 'c6000006-0000-4000-8000-000000000006', $driverB), 404));

    $ready = TripService::ready($mainTripId, 'c6000010-0000-4000-8000-000000000010', $driverA);
    $started = TripService::start($mainTripId, 'c6000011-0000-4000-8000-000000000011', $driverA);
    $startRetry = TripService::start($mainTripId, 'c6000011-0000-4000-8000-000000000011', $driverA);
    $eventCount = (int)$pdo->query('SELECT COUNT(*) FROM trip_events WHERE trip_id = ' . $mainTripId)->fetchColumn();
    $test('READY then START transitions succeed', $ready['trip']['status'] === 'READY' && $started['trip']['status'] === 'STARTED');
    $test('repeated action UUID is idempotent and creates no duplicate event', $startRetry['idempotent'] && $eventCount === 2);
    $test('reusing action UUID with a different payload is rejected', $expectStatus(fn() => TripService::start($mainTripId, 'c6000011-0000-4000-8000-000000000011', $driverA, ['notes' => 'payload berbeda']), 409));
    $test('STARTED → COMPLETED is rejected', $expectStatus(fn() => TripService::complete($mainTripId, 'c6000012-0000-4000-8000-000000000012', $driverA), 409));

    $arrived = TripService::arrival($mainTripId, 'c6000013-0000-4000-8000-000000000013', $driverA, [
        'latitude' => -7.2, 'longitude' => 110.0, 'accuracy_m' => 10,
    ]);
    $arrival = $pdo->query("SELECT location_status, distance_to_destination_m FROM trip_events WHERE trip_id = {$mainTripId} AND event_type = 'ARRIVAL'")->fetch(PDO::FETCH_ASSOC);
    $test('ARRIVAL records GPS and destination distance', $arrived['trip']['status'] === 'ARRIVED' && $arrival['location_status'] === 'VALID' && (float)$arrival['distance_to_destination_m'] === 0.0);
    $returning = TripService::returning($mainTripId, 'c6000014-0000-4000-8000-000000000014', $driverA);
    $completed = TripService::complete($mainTripId, 'c6000015-0000-4000-8000-000000000015', $driverA);
    $submit = TripService::submit($mainTripId, 'c6000016-0000-4000-8000-000000000016', $driverA);
    $gpsMissing = $pdo->query("SELECT location_status FROM trip_events WHERE trip_id = {$mainTripId} AND event_type = 'COMPLETED'")->fetchColumn();
    $test('RETURNING → COMPLETED → SUBMITTED are valid; missing completion GPS is reviewable',
        $returning['trip']['status'] === 'RETURNING' && $completed['trip']['status'] === 'COMPLETED'
        && $submit['trip']['status'] === 'SUBMITTED' && $gpsMissing === 'REVIEW_REQUIRED');
    $test('SUBMITTED is terminal', $expectStatus(fn() => TripService::start($mainTripId, 'c6000017-0000-4000-8000-000000000017', $driverA), 409));

    $auditCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity = 'trip' AND entity_id = '{$mainTripId}' AND action IN ('TRIP_CREATED','TRIP_READY','TRIP_STARTED','TRIP_ARRIVED','TRIP_RETURNING','TRIP_COMPLETED','TRIP_SUBMITTED')")->fetchColumn();
    $test('trip create and each successful lifecycle transition have audit records', $auditCount === 7);

    // Race two independent requests against one READY trip; row locking must permit exactly one START.
    $raceTrip = TripService::create($raceAssignment, 'c6000020-0000-4000-8000-000000000020', $admin);
    $raceTripId = (int)$raceTrip['trip']['id'];
    $ids['trips'][] = $raceTripId;
    TripService::ready($raceTripId, 'c6000021-0000-4000-8000-000000000021', $driverA);
    $workers = [];
    foreach (['c6000022-0000-4000-8000-000000000022', 'c6000023-0000-4000-8000-000000000023'] as $uuid) {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open([PHP_BINARY, __FILE__, '--race-worker', (string)$raceTripId, (string)$driverAId, $uuid, $goFile], $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start concurrent trip action worker.');
        }
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    file_put_contents($goFile, 'go');
    $workerResults = [];
    foreach ($workers as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $workerResults[] = ['code' => proc_close($process), 'out' => trim($stdout), 'err' => trim($stderr)];
    }
    $successes = count(array_filter($workerResults, static fn(array $r): bool => $r['code'] === 0 && $r['out'] === 'SUCCESS'));
    $conflicts = count(array_filter($workerResults, static fn(array $r): bool => $r['code'] === 3 && $r['out'] === 'CONFLICT'));
    $raceStarts = (int)$pdo->query("SELECT COUNT(*) FROM trip_events WHERE trip_id = {$raceTripId} AND event_type = 'START'")->fetchColumn();
    $test('two concurrent START requests produce exactly one success and one START event', $successes === 1 && $conflicts === 1 && $raceStarts === 1);
} catch (Throwable $e) {
    $failed++;
    fwrite(STDERR, '[FAIL] Integration exception: ' . $e->getMessage() . "\n");
} finally {
    @unlink($goFile);
    try {
        if ($userIds !== []) {
            $users = array_values($userIds);
            $placeholders = implode(',', array_fill(0, count($users), '?'));
            $pdo->prepare("DELETE FROM audit_logs WHERE user_id IN ({$placeholders})")->execute($users);
        }
        if ($tripIds !== []) {
            $trips = array_values(array_unique($tripIds));
            $placeholders = implode(',', array_fill(0, count($trips), '?'));
            $pdo->prepare("DELETE FROM audit_logs WHERE entity = 'trip' AND entity_id IN ({$placeholders})")->execute(array_map('strval', $trips));
            $pdo->prepare("DELETE FROM trip WHERE id IN ({$placeholders})")->execute($trips);
        }
        foreach (['assignments', 'drivers', 'vehicles', 'users'] as $table) {
            $values = array_values(array_unique($ids[$table]));
            if ($values !== []) {
                $placeholders = implode(',', array_fill(0, count($values), '?'));
                $pdo->prepare("DELETE FROM `{$table}` WHERE id IN ({$placeholders})")->execute($values);
            }
        }
    } catch (Throwable $cleanupError) {
        $failed++;
        fwrite(STDERR, '[FAIL] Fixture cleanup failed: ' . $cleanupError->getMessage() . "\n");
    }
}

echo "\nINTEGRATION: " . ($failed === 0 ? 'PASS' : 'FAIL') . " ({$passed} passed, {$failed} failed)\n";
exit($failed === 0 ? 0 : 1);
