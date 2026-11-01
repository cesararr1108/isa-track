<?php

declare(strict_types=1);

namespace Isa\Zones\Domain;

use Isa\Shared\Domain\ValueObject\GeoPoint;

/**
 * Elige la zona que cubre un punto. Si varias se cruzan, gana la más
 * pequeña (la más específica): un barrio dentro de una comuna cobra lo del barrio.
 */
final class ZoneResolver
{
    /** @param list<Zone> $zones */
    public function resolve(array $zones, GeoPoint $point): ?Zone
    {
        $best = null;
        foreach ($zones as $zone) {
            if (! $zone->covers($point)) {
                continue;
            }
            if ($best === null || $zone->area()->area() < $best->area()->area()) {
                $best = $zone;
            }
        }

        return $best;
    }
}
