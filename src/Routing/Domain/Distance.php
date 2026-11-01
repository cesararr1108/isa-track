<?php

declare(strict_types=1);

namespace Isa\Routing\Domain;

use Isa\Shared\Domain\ValueObject\GeoPoint;

/** Distancia en línea recta (fórmula de Haversine), en kilómetros. */
final class Distance
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function km(GeoPoint $a, GeoPoint $b): float
    {
        $dLat = deg2rad($b->latitude - $a->latitude);
        $dLng = deg2rad($b->longitude - $a->longitude);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a->latitude)) * cos(deg2rad($b->latitude)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($h)));
    }
}
