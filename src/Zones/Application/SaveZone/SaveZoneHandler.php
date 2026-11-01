<?php

declare(strict_types=1);

namespace Isa\Zones\Application\SaveZone;

use Isa\Shared\Application\TenantContext;
use Isa\Shared\Domain\ValueObject\Money;
use Isa\Zones\Domain\Exception\DuplicateZoneName;
use Isa\Zones\Domain\Exception\ZoneNotFound;
use Isa\Zones\Domain\Polygon;
use Isa\Zones\Domain\Zone;
use Isa\Zones\Domain\ZoneId;
use Isa\Zones\Domain\ZoneRepository;

/** Crea o edita una zona con su precio de domicilio. */
final class SaveZoneHandler
{
    public function __construct(
        private readonly ZoneRepository $zones,
        private readonly TenantContext $tenant,
    ) {}

    public function __invoke(SaveZoneCommand $command): Zone
    {
        $companyId = $this->tenant->companyId();
        $area = Polygon::fromPairs($command->polygon);
        $fee = new Money($command->deliveryFee);
        $id = $command->zoneId !== null ? ZoneId::fromString($command->zoneId) : null;

        if ($this->zones->nameTaken($companyId, trim($command->name), $id)) {
            throw new DuplicateZoneName(trim($command->name));
        }

        if ($id === null) {
            $zone = Zone::create(ZoneId::generate(), $companyId, $command->name, $command->color, $area, $fee, $command->estimatedMinutes);
            if (! $command->active) {
                $zone->update($command->name, $command->color, $area, $fee, $command->estimatedMinutes, false);
            }
        } else {
            $zone = $this->zones->find($companyId, $id) ?? throw new ZoneNotFound($command->zoneId);
            $zone->update($command->name, $command->color, $area, $fee, $command->estimatedMinutes, $command->active);
        }

        $this->zones->save($zone);

        return $zone;
    }
}
