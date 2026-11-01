<?php

declare(strict_types=1);

namespace Isa\Zones\Application\QuoteDeliveryFee;

use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Zones\Domain\Exception\LocationOutsideCoverage;
use Isa\Zones\Domain\Zone;
use Isa\Zones\Domain\ZoneRepository;
use Isa\Zones\Domain\ZoneResolver;

/** ¿Cuánto cuesta el domicilio a este punto? Responde con la zona que lo cubre. */
final class QuoteDeliveryFeeHandler
{
    public function __construct(
        private readonly ZoneRepository $zones,
        private readonly ZoneResolver $resolver,
    ) {}

    public function zoneFor(CompanyId $companyId, GeoPoint $point): ?Zone
    {
        return $this->resolver->resolve($this->zones->allOf($companyId, onlyActive: true), $point);
    }

    /** @return array{zone_id: string, zone_name: string, color: string, delivery_fee: int, estimated_minutes: int|null} */
    public function __invoke(CompanyId $companyId, float $latitude, float $longitude): array
    {
        $zone = $this->zoneFor($companyId, new GeoPoint($latitude, $longitude)) ?? throw new LocationOutsideCoverage;

        return [
            'zone_id' => $zone->id()->value(),
            'zone_name' => $zone->name(),
            'color' => $zone->color(),
            'delivery_fee' => $zone->deliveryFee()->amount,
            'estimated_minutes' => $zone->estimatedMinutes(),
        ];
    }
}
