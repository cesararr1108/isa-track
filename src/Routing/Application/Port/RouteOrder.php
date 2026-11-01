<?php

declare(strict_types=1);

namespace Isa\Routing\Application\Port;

/** Lo que Rutas necesita saber de un pedido. */
final class RouteOrder
{
    public function __construct(
        public readonly string $id,
        public readonly string $trackingCode,
        public readonly string $status,
        public readonly string $recipientName,
        public readonly string $addressLine,
        public readonly ?string $neighborhood,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly float $weightKg,
        public readonly int $packages,
        public readonly ?int $cashOnDelivery,
        public readonly ?string $carrierId,
    ) {}
}
