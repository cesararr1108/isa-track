<?php

declare(strict_types=1);

namespace Isa\Zones\Application\DeleteZone;

use Isa\Shared\Application\TenantContext;
use Isa\Zones\Domain\Exception\ZoneNotFound;
use Isa\Zones\Domain\ZoneId;
use Isa\Zones\Domain\ZoneRepository;

final class DeleteZoneHandler
{
    public function __construct(
        private readonly ZoneRepository $zones,
        private readonly TenantContext $tenant,
    ) {}

    public function __invoke(string $zoneId): void
    {
        $zone = $this->zones->find($this->tenant->companyId(), ZoneId::fromString($zoneId))
            ?? throw new ZoneNotFound($zoneId);

        $this->zones->delete($zone);
    }
}
