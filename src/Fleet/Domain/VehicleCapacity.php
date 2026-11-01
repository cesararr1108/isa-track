<?php

declare(strict_types=1);

namespace Isa\Fleet\Domain;

use InvalidArgumentException;

/**
 * Cuánto puede llevar un mensajero en un recorrido: peso (kg) y/o número de paquetes.
 * Un valor null significa "sin límite definido".
 */
final class VehicleCapacity
{
    public function __construct(
        public readonly ?float $maxWeightKg = null,
        public readonly ?int $maxPackages = null,
    ) {
        if ($maxWeightKg !== null && $maxWeightKg <= 0) {
            throw new InvalidArgumentException('La capacidad en kg debe ser mayor que cero.');
        }
        if ($maxPackages !== null && $maxPackages <= 0) {
            throw new InvalidArgumentException('La capacidad en paquetes debe ser mayor que cero.');
        }
    }

    public static function unlimited(): self
    {
        return new self;
    }

    public function allows(float $weightKg, int $packages): bool
    {
        return ($this->maxWeightKg === null || $weightKg <= $this->maxWeightKg + 1e-9)
            && ($this->maxPackages === null || $packages <= $this->maxPackages);
    }
}
