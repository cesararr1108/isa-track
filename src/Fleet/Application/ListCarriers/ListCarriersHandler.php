<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\ListCarriers;

use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Shared\Application\TenantContext;

final class ListCarriersHandler
{
    public function __construct(
        private readonly CarrierRepository $carriers,
        private readonly TenantContext $tenant,
    ) {}

    /** @return list<array<string, mixed>> */
    public function __invoke(bool $onlyActive = false): array
    {
        return array_map(fn (Carrier $c) => [
            'id' => $c->id()->value(),
            'name' => $c->name(),
            'type' => $c->type()->value,
            'phone' => $c->phone(),
            'vehicle_plate' => $c->vehiclePlate(),
            'active' => $c->isActive(),
        ], $this->carriers->allOf($this->tenant->companyId(), $onlyActive));
    }
}
