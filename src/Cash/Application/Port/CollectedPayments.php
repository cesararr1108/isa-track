<?php

declare(strict_types=1);

namespace Isa\Cash\Application\Port;

use Isa\Cash\Domain\BusinessDate;
use Isa\Cash\Domain\CollectionSummary;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

/** Lo recaudado por un conductor en un día (lo provee el módulo Pedidos). */
interface CollectedPayments
{
    public function summaryFor(CompanyId $companyId, CarrierId $carrierId, BusinessDate $date): CollectionSummary;
}
