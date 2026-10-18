<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\RegisterCarrier;

use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Fleet\Domain\CarrierType;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Domain\ValueObject\CarrierId;

final class RegisterCarrierHandler
{
    public function __construct(
        private readonly CarrierRepository $carriers,
        private readonly TenantContext $tenant,
    ) {}

    public function __invoke(RegisterCarrierCommand $command): CarrierId
    {
        $carrier = Carrier::register(
            CarrierId::generate(),
            $this->tenant->companyId(),
            $command->name,
            CarrierType::from($command->type),
            $command->phone,
            $command->vehiclePlate,
        );

        $this->carriers->save($carrier);

        return $carrier->id();
    }
}
