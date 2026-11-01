<?php

declare(strict_types=1);

namespace Isa\Zones\Domain;

use Isa\Shared\Domain\ValueObject\CompanyId;

interface ZoneRepository
{
    public function save(Zone $zone): void;

    public function find(CompanyId $companyId, ZoneId $id): ?Zone;

    /** @return list<Zone> */
    public function allOf(CompanyId $companyId, bool $onlyActive = false): array;

    public function delete(Zone $zone): void;

    public function nameTaken(CompanyId $companyId, string $name, ?ZoneId $except = null): bool;
}
