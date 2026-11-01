<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use Isa\Shared\Domain\ValueObject\Money;

/** Lo que cuesta el domicilio y la zona de la que sale la tarifa. */
final class DeliveryCharge
{
    public function __construct(
        public readonly Money $fee,
        public readonly ?string $zoneId = null,
        public readonly ?string $zoneName = null,
    ) {}
}
