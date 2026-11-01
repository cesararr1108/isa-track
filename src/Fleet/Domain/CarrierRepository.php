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

    /** ¿Otro mensajero de la empresa ya usa esta cédula? */
    public function documentTaken(CompanyId $companyId, string $documentNumber, ?CarrierId $except = null): bool;
}
