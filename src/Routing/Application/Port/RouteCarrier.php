<?php

declare(strict_types=1);

namespace Isa\Routing\Application\Port;

final class RouteCarrier
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $active,
        public readonly ?string $vehiclePlate,
        public readonly ?string $vehicleType,
        public readonly ?float $capacityKg,
        public readonly ?int $capacityPackages,
    ) {}
}
