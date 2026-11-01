<?php

declare(strict_types=1);

namespace Isa\Zones\Application\SaveZone;

final class SaveZoneCommand
{
    /** @param list<array{0: float, 1: float}> $polygon [[lat, lng], ...] */
    public function __construct(
        public readonly string $name,
        public readonly string $color,
        public readonly array $polygon,
        public readonly int $deliveryFee,
        public readonly ?int $estimatedMinutes = null,
        public readonly bool $active = true,
        /** null = crear; un id = editar esa zona. */
        public readonly ?string $zoneId = null,
    ) {}
}
