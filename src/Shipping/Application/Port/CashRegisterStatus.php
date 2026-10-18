<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\Port;

use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

/** Pedidos pregunta al módulo Caja si el día del conductor ya se cerró. */
interface CashRegisterStatus
{
    /** @param string $date Fecha de negocio en formato Y-m-d */
    public function isClosed(CompanyId $companyId, CarrierId $carrierId, string $date): bool;
}
