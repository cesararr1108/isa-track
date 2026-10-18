<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Persistence;

use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Fleet\Domain\CarrierType;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

final class EloquentCarrierRepository implements CarrierRepository
{
    public function save(Carrier $carrier): void
    {
        CarrierModel::query()->updateOrCreate(
            ['id' => $carrier->id()->value()],
            [
                'company_id' => $carrier->companyId()->value(),
                'name' => $carrier->name(),
                'type' => $carrier->type()->value,
                'phone' => $carrier->phone(),
                'vehicle_plate' => $carrier->vehiclePlate(),
                'active' => $carrier->isActive(),
            ],
        );
    }

    public function find(CompanyId $companyId, CarrierId $id): ?Carrier
    {
        $row = CarrierModel::query()
            ->where('company_id', $companyId->value())
            ->find($id->value());

        return $row === null ? null : $this->toDomain($row);
    }

    public function allOf(CompanyId $companyId, bool $onlyActive = false): array
    {
        return CarrierModel::query()
            ->where('company_id', $companyId->value())
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('name')
            ->get()
            ->map(fn (CarrierModel $row) => $this->toDomain($row))
            ->all();
    }

    private function toDomain(CarrierModel $row): Carrier
    {
        return Carrier::restore(
            CarrierId::fromString($row->id),
            CompanyId::fromString($row->company_id),
            $row->name,
            CarrierType::from($row->type),
            $row->phone,
            $row->vehicle_plate,
            $row->active,
        );
    }
}
