<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Persistence;

use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierProfile;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Fleet\Domain\CarrierType;
use Isa\Fleet\Domain\VehicleCapacity;
use Isa\Fleet\Domain\VehicleType;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

final class EloquentCarrierRepository implements CarrierRepository
{
    public function save(Carrier $carrier): void
    {
        $p = $carrier->profile();

        CarrierModel::query()->updateOrCreate(
            ['id' => $carrier->id()->value()],
            [
                'company_id' => $carrier->companyId()->value(),
                'name' => $carrier->name(),
                'type' => $carrier->type()->value,
                'document_number' => $p->documentNumber,
                'phone' => $p->phone,
                'email' => $p->email,
                'vehicle_plate' => $p->vehiclePlate,
                'vehicle_type' => $p->vehicleType?->value,
                'capacity_kg' => $p->capacity->maxWeightKg,
                'capacity_packages' => $p->capacity->maxPackages,
                'photo_path' => $carrier->photoPath(),
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

    public function documentTaken(CompanyId $companyId, string $documentNumber, ?CarrierId $except = null): bool
    {
        return CarrierModel::query()
            ->where('company_id', $companyId->value())
            ->where('document_number', $documentNumber)
            ->when($except, fn ($q) => $q->where('id', '!=', $except->value()))
            ->exists();
    }

    private function toDomain(CarrierModel $row): Carrier
    {
        return Carrier::restore(
            CarrierId::fromString($row->id),
            CompanyId::fromString($row->company_id),
            $row->name,
            CarrierType::from($row->type),
            new CarrierProfile(
                documentNumber: $row->document_number,
                phone: $row->phone,
                vehiclePlate: $row->vehicle_plate,
                vehicleType: $row->vehicle_type !== null ? VehicleType::from($row->vehicle_type) : null,
                capacity: new VehicleCapacity($row->capacity_kg, $row->capacity_packages),
                email: $row->email,
            ),
            $row->photo_path,
            $row->active,
        );
    }
}
