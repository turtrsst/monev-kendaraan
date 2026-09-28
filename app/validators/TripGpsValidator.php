<?php

declare(strict_types=1);

namespace App\Validators;

use App\Services\SettingsService;

/** Event-based GPS classification; location is supporting evidence, never proof. */
final class TripGpsValidator
{
    /** @return array{latitude:?float,longitude:?float,accuracy_m:?float,distance_to_destination_m:?float,location_status:string,reason:string} */
    public static function evaluate(
        mixed $latitude,
        mixed $longitude,
        mixed $accuracy,
        string $eventType,
        mixed $destinationLatitude = null,
        mixed $destinationLongitude = null,
        ?array $configuration = null
    ): array {
        $maxAccuracy = max(1, (int)($configuration['max_accuracy_m'] ?? SettingsService::getInt('trip.gps_max_accuracy_m', 100)));
        $radius = max(1, (int)($configuration['radius_m'] ?? SettingsService::getInt('trip.destination_radius_default_m', 100)));
        $tolerance = max(0, (int)($configuration['tolerance_m'] ?? SettingsService::getInt('trip.destination_tolerance_m', 50)));
        $lat = self::number($latitude);
        $lon = self::number($longitude);
        $acc = self::number($accuracy);

        $result = [
            'latitude' => null,
            'longitude' => null,
            'accuracy_m' => null,
            'distance_to_destination_m' => null,
            'location_status' => 'REVIEW_REQUIRED',
            'reason' => 'GPS tidak tersedia.',
        ];

        if ($lat === null || $lon === null || $acc === null) {
            return $result;
        }
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 || $acc <= 0) {
            $result['reason'] = 'Koordinat atau akurasi GPS berada di luar nilai yang mungkin.';
            return $result;
        }

        $result['latitude'] = $lat;
        $result['longitude'] = $lon;
        if ($acc > 9999999.99) {
            $result['reason'] = 'Nilai akurasi GPS berada di luar kapasitas pengukuran.';
            return $result;
        }
        $result['accuracy_m'] = $acc;
        if ($acc > ($maxAccuracy * 3)) {
            $result['reason'] = 'Akurasi GPS sangat rendah.';
            return $result;
        }

        $accuracyWarning = $acc > $maxAccuracy;
        if ($eventType === 'ARRIVAL') {
            $destLat = self::number($destinationLatitude);
            $destLon = self::number($destinationLongitude);
            if ($destLat === null || $destLon === null || $destLat < -90 || $destLat > 90 || $destLon < -180 || $destLon > 180) {
                $result['location_status'] = 'WARNING';
                $result['reason'] = $accuracyWarning
                    ? 'Koordinat tujuan belum ditetapkan; akurasi GPS perlu diperhatikan.'
                    : 'Koordinat tujuan belum ditetapkan; jarak tidak dapat diverifikasi.';
                return $result;
            }
            $distance = self::haversine($lat, $lon, $destLat, $destLon);
            $result['distance_to_destination_m'] = round($distance, 2);
            if ($accuracyWarning) {
                $result['location_status'] = 'WARNING';
                $result['reason'] = 'Akurasi GPS melebihi ambang konfigurasi.';
            } elseif ($distance <= $radius) {
                $result['location_status'] = 'VALID';
                $result['reason'] = 'Posisi berada dalam radius tujuan.';
            } elseif ($distance <= ($radius + $tolerance)) {
                $result['location_status'] = 'WARNING';
                $result['reason'] = 'Posisi sedikit di luar radius tujuan.';
            } else {
                $result['location_status'] = 'REVIEW_REQUIRED';
                $result['reason'] = 'Posisi berada di luar radius tujuan dan toleransinya.';
            }
            return $result;
        }

        $result['location_status'] = $accuracyWarning ? 'WARNING' : 'VALID';
        $result['reason'] = $accuracyWarning
            ? 'Akurasi GPS melebihi ambang konfigurasi.'
            : 'Koordinat dan akurasi GPS dapat digunakan sebagai bukti pendukung.';
        return $result;
    }

    public static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusM = 6371008.8;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lon2 - $lon1);
        $a = sin($deltaPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;
        return $earthRadiusM * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    private static function number(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float)$value;
        return is_finite($number) ? $number : null;
    }
}
