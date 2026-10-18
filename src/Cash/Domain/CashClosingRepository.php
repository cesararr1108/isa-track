<?php

declare(strict_types=1);

namespace Isa\Cash\Domain;

use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

interface CashClosingRepository
{
    public function save(CashClosing $closing): void;

    public function findFor(CompanyId $companyId, CarrierId $carrierId, BusinessDate $date): ?CashClosing;

    /** @return list<CashClosing> */
    public function listFor(CompanyId $companyId, BusinessDate $date): array;
}
