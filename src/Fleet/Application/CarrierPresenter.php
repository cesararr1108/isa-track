<?php

declare(strict_types=1);

namespace Isa\Fleet\Application;

use Isa\Fleet\Domain\Carrier;

final class CarrierPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(Carrier $c): array
    {
        $p = $c->profile();

        return [
            'id' => $c->id()->value(),
            'name' => $c->name(),
            'type' => $c->type()->value,
            'document_number' => $p->documentNumber,
            'phone' => $p->phone,
            'email' => $p->email,
            'vehicle_plate' => $p->vehiclePlate,
            'vehicle_type' => $p->vehicleType?->value,
            'capacity_kg' => $p->capacity->maxWeightKg,
            'capacity_packages' => $p->capacity->maxPackages,
            'has_photo' => $c->photoPath() !== null,
            'last_location' => $c->lastLocation() === null ? null : [
                'latitude' => $c->lastLocation()->latitude,
                'longitude' => $c->lastLocation()->longitude,
                'at' => $c->lastLocationAt()?->format(DATE_ATOM),
            ],
            'active' => $c->isActive(),
        ];
    }
}
