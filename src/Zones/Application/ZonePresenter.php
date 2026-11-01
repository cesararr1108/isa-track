<?php

declare(strict_types=1);

namespace Isa\Zones\Application;

use Isa\Zones\Domain\Zone;

final class ZonePresenter
{
    /** @return array<string, mixed> */
    public static function toArray(Zone $zone): array
    {
        $center = $zone->area()->centroid();

        return [
            'id' => $zone->id()->value(),
            'name' => $zone->name(),
            'color' => $zone->color(),
            'delivery_fee' => $zone->deliveryFee()->amount,
            'estimated_minutes' => $zone->estimatedMinutes(),
            'active' => $zone->isActive(),
            'polygon' => $zone->area()->toPairs(),
            'center' => [$center->latitude, $center->longitude],
        ];
    }
}
