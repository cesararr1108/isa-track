<?php

declare(strict_types=1);

namespace Isa\Routing\Infrastructure\Adapter;

use Isa\Fleet\Domain\CarrierRepository;
use Isa\Routing\Application\Port\RouteCarrier;
use Isa\Routing\Application\Port\RouteCarriers;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

final class FleetRouteCarriers implements RouteCarriers
{
    public function __construct(private readonly CarrierRepository $carriers) {}

    public function find(CompanyId $companyId, string $carrierId): ?RouteCarrier
    {
        $c = preg_match('/^[0-9a-f-]{36}$/i', $carrierId) ? $this->carriers->find($companyId, CarrierId::fromString($carrierId)) : null;
        if ($c === null) {
            return null;
        }
        $p = $c->profile();

        return new RouteCarrier(
            $c->id()->value(),
            $c->name(),
            $c->isActive(),
            $p->vehiclePlate,
            $p->vehicleType?->value,
            $p->capacity->maxWeightKg,
            $p->capacity->maxPackages,
        );
    }
}
