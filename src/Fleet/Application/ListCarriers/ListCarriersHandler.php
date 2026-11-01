<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\ListCarriers;

use Isa\Fleet\Application\CarrierPresenter;
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
        return array_map(
            CarrierPresenter::toArray(...),
            $this->carriers->allOf($this->tenant->companyId(), $onlyActive),
        );
    }
}
