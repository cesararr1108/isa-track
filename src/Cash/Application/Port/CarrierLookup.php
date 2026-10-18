<?php

declare(strict_types=1);

namespace Isa\Cash\Application\Port;

use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

interface CarrierLookup
{
    public function exists(CompanyId $companyId, CarrierId $carrierId): bool;
}
