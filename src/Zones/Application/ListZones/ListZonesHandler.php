<?php

declare(strict_types=1);

namespace Isa\Zones\Application\ListZones;

use Isa\Shared\Application\TenantContext;
use Isa\Zones\Application\ZonePresenter;
use Isa\Zones\Domain\ZoneRepository;

final class ListZonesHandler
{
    public function __construct(
        private readonly ZoneRepository $zones,
        private readonly TenantContext $tenant,
    ) {}

    /** @return list<array<string, mixed>> */
    public function __invoke(bool $onlyActive = false): array
    {
        return array_map(ZonePresenter::toArray(...), $this->zones->allOf($this->tenant->companyId(), $onlyActive));
    }
}
