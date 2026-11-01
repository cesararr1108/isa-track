<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\Port;

use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shipping\Domain\DeliveryCharge;

/** Pedidos le pregunta a Zonas cuánto vale el domicilio a una ubicación. */
interface DeliveryFeeQuoter
{
    public function quote(CompanyId $companyId, GeoPoint $location): ?DeliveryCharge;
}
