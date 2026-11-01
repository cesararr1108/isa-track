<?php

declare(strict_types=1);

namespace Isa\Routing\Infrastructure\Adapter;

use Isa\Routing\Application\Port\RouteDepot;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;

/** La bodega sale de config/isa.php (ISA_DEPOT_LAT / ISA_DEPOT_LNG). Luego: una por empresa. */
final class ConfigRouteDepot implements RouteDepot
{
    public function __construct(private readonly float $latitude, private readonly float $longitude) {}

    public function of(CompanyId $companyId): GeoPoint
    {
        return new GeoPoint($this->latitude, $this->longitude);
    }
}
