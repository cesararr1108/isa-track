<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Adapter;

use Isa\Cash\Domain\BusinessDate;
use Isa\Cash\Domain\CashClosingRepository;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shipping\Application\Port\CashRegisterStatus;

/** Adaptador: responde el puerto de Pedidos consultando el módulo Caja. */
final class CashModuleRegisterStatus implements CashRegisterStatus
{
    public function __construct(private readonly CashClosingRepository $closings) {}

    public function isClosed(CompanyId $companyId, CarrierId $carrierId, string $date): bool
    {
        return $this->closings->findFor($companyId, $carrierId, new BusinessDate($date)) !== null;
    }
}
