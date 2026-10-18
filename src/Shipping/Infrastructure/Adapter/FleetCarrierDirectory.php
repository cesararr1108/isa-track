<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Adapter;

use Isa\Fleet\Domain\CarrierRepository;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shipping\Application\Port\CarrierDirectory;

/** Adaptador: responde el puerto CarrierDirectory usando el módulo Flota. */
final class FleetCarrierDirectory implements CarrierDirectory
{
    public function __construct(private readonly CarrierRepository $carriers) {}

    public function isAvailable(CompanyId $companyId, CarrierId $carrierId): bool
    {
        return $this->carriers->find($companyId, $carrierId)?->isActive() ?? false;
    }

    public function displayName(CompanyId $companyId, CarrierId $carrierId): ?string
    {
        return $this->carriers->find($companyId, $carrierId)?->name();
    }
}
