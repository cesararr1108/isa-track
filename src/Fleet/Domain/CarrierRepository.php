<?php

declare(strict_types=1);

namespace Isa\Fleet\Domain;

use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

interface CarrierRepository
{
    public function save(Carrier $carrier): void;

    public function find(CompanyId $companyId, CarrierId $id): ?Carrier;

    /** @return list<Carrier> */
    public function allOf(CompanyId $companyId, bool $onlyActive = false): array;
}
