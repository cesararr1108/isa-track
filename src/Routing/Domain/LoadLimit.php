<?php

declare(strict_types=1);

namespace Isa\Routing\Domain;

use Isa\Routing\Domain\Exception\RouteExceedsCapacity;

/** Cuánto aguanta el vehículo de la ruta (null = sin límite). */
final class LoadLimit
{
    public function __construct(
        public readonly ?float $maxWeightKg = null,
        public readonly ?int $maxPackages = null,
    ) {}

    /** @param list<RouteStop> $stops */
    public function assertFits(array $stops): void
    {
        $weight = array_sum(array_map(fn (RouteStop $s) => $s->weightKg, $stops));
        $packages = array_sum(array_map(fn (RouteStop $s) => $s->packages, $stops));

        if ($this->maxWeightKg !== null && $weight > $this->maxWeightKg + 1e-9) {
            throw new RouteExceedsCapacity(sprintf('%s kg de %s kg permitidos', self::n($weight), self::n($this->maxWeightKg)));
        }
        if ($this->maxPackages !== null && $packages > $this->maxPackages) {
            throw new RouteExceedsCapacity(sprintf('%d paquetes de %d permitidos', $packages, $this->maxPackages));
        }
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }
}
