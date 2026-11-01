<?php

declare(strict_types=1);

namespace Isa\Routing\Domain;

use Isa\Shared\Domain\ValueObject\GeoPoint;

/** Una parada de la ruta: un pedido con su ubicación y su carga. */
final class RouteStop
{
    public function __construct(
        public readonly string $orderId,
        public readonly GeoPoint $location,
        public readonly float $weightKg = 0.0,
        public readonly int $packages = 1,
    ) {}
}
