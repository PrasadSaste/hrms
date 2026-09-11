<?php

namespace App\Support;

/**
 * Distance between two points on the earth.
 *
 * The haversine formula on a sphere of mean radius 6,371 km. Over the few
 * hundred metres a geofence cares about it is accurate to well under a metre,
 * which is far finer than the fix a phone reports in the first place.
 */
final class Geo
{
    /** Mean earth radius in metres. */
    public const EARTH_RADIUS_METRES = 6_371_000;

    public static function distanceInMetres(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
    ): float {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude))
            * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METRES * 2 * asin(min(1.0, sqrt($a)));
    }

    /** A distance said the way a person would say it. */
    public static function describeDistance(?int $metres): string
    {
        if ($metres === null) {
            return '—';
        }

        return $metres < 1000
            ? $metres.' m'
            : number_format($metres / 1000, 1).' km';
    }

    /** A link that opens the point on a map, or null when there is no fix. */
    public static function mapUrl(float|string|null $latitude, float|string|null $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=17/%s/%s',
            $latitude, $longitude, $latitude, $longitude);
    }
}
