<?php

declare(strict_types=1);

namespace Isa\Zones\Infrastructure\Persistence;

use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\Money;
use Isa\Zones\Domain\Polygon;
use Isa\Zones\Domain\Zone;
use Isa\Zones\Domain\ZoneId;
use Isa\Zones\Domain\ZoneRepository;

final class EloquentZoneRepository implements ZoneRepository
{
    public function save(Zone $zone): void
    {
        ZoneModel::query()->updateOrCreate(['id' => $zone->id()->value()], [
            'company_id' => $zone->companyId()->value(),
            'name' => $zone->name(),
            'color' => $zone->color(),
            'polygon' => $zone->area()->toPairs(),
            'delivery_fee' => $zone->deliveryFee()->amount,
            'estimated_minutes' => $zone->estimatedMinutes(),
            'active' => $zone->isActive(),
        ]);
    }

    public function find(CompanyId $companyId, ZoneId $id): ?Zone
    {
        $row = ZoneModel::query()->where('company_id', $companyId->value())->find($id->value());

        return $row === null ? null : $this->toDomain($row);
    }

    public function allOf(CompanyId $companyId, bool $onlyActive = false): array
    {
        return ZoneModel::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId->value())
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('name')
            ->get()
            ->map(fn (ZoneModel $row) => $this->toDomain($row))
            ->all();
    }

    public function delete(Zone $zone): void
    {
        ZoneModel::query()->where('company_id', $zone->companyId()->value())->whereKey($zone->id()->value())->delete();
    }

    public function nameTaken(CompanyId $companyId, string $name, ?ZoneId $except = null): bool
    {
        return ZoneModel::query()
            ->where('company_id', $companyId->value())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($except, fn ($q) => $q->where('id', '!=', $except->value()))
            ->exists();
    }

    private function toDomain(ZoneModel $row): Zone
    {
        return Zone::restore(
            ZoneId::fromString($row->id),
            CompanyId::fromString($row->company_id),
            $row->name,
            $row->color,
            Polygon::fromPairs($row->polygon),
            new Money($row->delivery_fee),
            $row->estimated_minutes,
            $row->active,
        );
    }
}
