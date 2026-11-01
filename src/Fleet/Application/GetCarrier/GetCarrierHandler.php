<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\GetCarrier;

use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Fleet\Domain\Exception\CarrierNotFound;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Domain\ValueObject\CarrierId;

final class GetCarrierHandler
{
    public function __construct(
        private readonly CarrierRepository $carriers,
        private readonly TenantContext $tenant,
    ) {}

    public function __invoke(string $carrierId): Carrier
    {
        return $this->carriers->find($this->tenant->companyId(), CarrierId::fromString($carrierId))
            ?? throw new CarrierNotFound($carrierId);
    }
}
