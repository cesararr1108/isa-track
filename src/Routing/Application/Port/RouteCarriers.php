<?php

declare(strict_types=1);

namespace Isa\Routing\Application\Port;

use Isa\Shared\Domain\ValueObject\CompanyId;

/** Puerto hacia Flota. */
interface RouteCarriers
{
    public function find(CompanyId $companyId, string $carrierId): ?RouteCarrier;
}
