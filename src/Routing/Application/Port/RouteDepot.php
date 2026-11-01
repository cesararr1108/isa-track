<?php

declare(strict_types=1);

namespace Isa\Routing\Application\Port;

use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;

/** Punto de partida de las rutas (la bodega de la empresa). */
interface RouteDepot
{
    public function of(CompanyId $companyId): GeoPoint;
}
