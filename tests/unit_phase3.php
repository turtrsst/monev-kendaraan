<?php
/** Pure unit tests for the Phase 3 state machine and GPS calculations. */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\TripStateMachine;
use App\Validators\TripGpsValidator;

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

$expected = [
    'ASSIGNED' => 'READY',
    'READY' => 'STARTED',
    'STARTED' => 'ARRIVED',
    'ARRIVED' => 'RETURNING',
    'RETURNING' => 'COMPLETED',
    'COMPLETED' => 'SUBMITTED',
];
foreach ($expected as $from => $to) {
    $test("state transition {$from} → {$to} accepted", TripStateMachine::allows($from, $to));
}
foreach ([
    ['STARTED', 'COMPLETED'], ['STARTED', 'RETURNING'], ['ARRIVED', 'STARTED'],
    ['COMPLETED', 'STARTED'], ['SUBMITTED', 'STARTED'], ['ASSIGNED', 'STARTED'],
] as [$from, $to]) {
    $test("state transition {$from} → {$to} rejected", !TripStateMachine::allows($from, $to));
}
$test('SUBMITTED is terminal', TripStateMachine::next('SUBMITTED') === null);

$gpsConfig = ['radius_m' => 100, 'tolerance_m' => 50, 'max_accuracy_m' => 100];
$valid = TripGpsValidator::evaluate(-7.2, 110.0, 12, 'ARRIVAL', -7.2, 110.0, $gpsConfig);
$test('GPS valid inside destination radius', $valid['location_status'] === 'VALID' && $valid['distance_to_destination_m'] === 0.0);
$invalid = TripGpsValidator::evaluate(91, 110, 10, 'ARRIVAL', 0, 0, $gpsConfig);
$test('GPS impossible coordinate requires review', $invalid['location_status'] === 'REVIEW_REQUIRED' && $invalid['latitude'] === null);
$poorAccuracy = TripGpsValidator::evaluate(0, 0, 150, 'ARRIVAL', 0, 0, $gpsConfig);
$test('GPS poor accuracy is a warning', $poorAccuracy['location_status'] === 'WARNING');
$severeAccuracy = TripGpsValidator::evaluate(0, 0, 301, 'START', null, null, $gpsConfig);
$test('GPS severely inaccurate requires review', $severeAccuracy['location_status'] === 'REVIEW_REQUIRED');
$impossibleAccuracy = TripGpsValidator::evaluate(0, 0, 1.0e20, 'START', null, null, $gpsConfig);
$test('GPS accuracy outside storage range is reviewed without overflow data', $impossibleAccuracy['location_status'] === 'REVIEW_REQUIRED' && $impossibleAccuracy['accuracy_m'] === null);
$outsideRadius = TripGpsValidator::evaluate(0, 0.0018, 10, 'ARRIVAL', 0, 0, $gpsConfig);
$test('GPS beyond radius and tolerance requires review', $outsideRadius['location_status'] === 'REVIEW_REQUIRED');
$slightlyOutside = TripGpsValidator::evaluate(0, 0.0011, 10, 'ARRIVAL', 0, 0, $gpsConfig);
$test('GPS slightly outside radius is a warning', $slightlyOutside['location_status'] === 'WARNING');
$missing = TripGpsValidator::evaluate(null, null, null, 'START', null, null, $gpsConfig);
$test('missing GPS is recorded as review required', $missing['location_status'] === 'REVIEW_REQUIRED');
$noDestination = TripGpsValidator::evaluate(0, 0, 10, 'ARRIVAL', null, null, $gpsConfig);
$test('missing destination coordinates do not claim distance validation', $noDestination['location_status'] === 'WARNING' && $noDestination['distance_to_destination_m'] === null);
$distance = TripGpsValidator::haversine(0, 0, 0, 1);
$test('Haversine distance is geographically plausible', $distance > 111000 && $distance < 112000);

$failed === 0
    ? print("\nUNIT: PASS ({$passed} assertions)\n")
    : print("\nUNIT: FAIL ({$passed} passed, {$failed} failed)\n");
exit($failed === 0 ? 0 : 1);
