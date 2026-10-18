<?php

declare(strict_types=1);

namespace Isa\Cash\Infrastructure\Adapter;

use Isa\Cash\Application\Port\CarrierLookup;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

final class FleetCarrierLookup implements CarrierLookup
{
    public function __construct(private readonly CarrierRepository $carriers) {}

    public function exists(CompanyId $companyId, CarrierId $carrierId): bool
    {
        return $this->carriers->find($companyId, $carrierId) !== null;
    }
}
