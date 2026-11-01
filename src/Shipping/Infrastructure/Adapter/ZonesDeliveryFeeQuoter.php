<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Adapter;

use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shipping\Application\Port\DeliveryFeeQuoter;
use Isa\Shipping\Domain\DeliveryCharge;
use Isa\Zones\Application\QuoteDeliveryFee\QuoteDeliveryFeeHandler;

/** Adaptador: la tarifa sale de la zona que cubre la dirección (módulo Zonas). */
final class ZonesDeliveryFeeQuoter implements DeliveryFeeQuoter
{
    public function __construct(private readonly QuoteDeliveryFeeHandler $zones) {}

    public function quote(CompanyId $companyId, GeoPoint $location): ?DeliveryCharge
    {
        $zone = $this->zones->zoneFor($companyId, $location);

        return $zone === null ? null : new DeliveryCharge($zone->deliveryFee(), $zone->id()->value(), $zone->name());
    }
}
