<?php

declare(strict_types=1);

namespace Isa\Routing\Application\PlanRoute;

final class PlanRouteCommand
{
    /** @param list<string> $orderIds en el orden deseado (si optimize=false) */
    public function __construct(
        public readonly string $carrierId,
        public readonly string $date,
        public readonly array $orderIds,
        public readonly ?string $name = null,
        public readonly bool $optimize = true,
        public readonly ?float $startLatitude = null,
        public readonly ?float $startLongitude = null,
        public readonly ?string $plannedBy = null,
    ) {}
}
