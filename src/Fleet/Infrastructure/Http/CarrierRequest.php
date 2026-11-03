<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Isa\Fleet\Application\CarrierData;
use Isa\Fleet\Domain\CarrierType;
use Isa\Fleet\Domain\VehicleType;

/** Validación de formato de un transportador. La usan la API y el panel web. */
final class CarrierRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(CarrierType::class)],
            'document_number' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'vehicle_plate' => ['nullable', 'string', 'max:10'],
            'vehicle_type' => ['nullable', Rule::enum(VehicleType::class)],
            'capacity_kg' => ['nullable', 'numeric', 'gt:0', 'max:50000'],
            'capacity_packages' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre', 'type' => 'tipo', 'document_number' => 'cédula', 'phone' => 'teléfono',
            'email' => 'correo', 'vehicle_plate' => 'placa', 'vehicle_type' => 'vehículo',
            'capacity_kg' => 'capacidad en kg', 'capacity_packages' => 'capacidad en paquetes',
        ];
    }

    public function toData(): CarrierData
    {
        $d = $this->validated();

        return new CarrierData(
            name: $d['name'],
            type: $d['type'],
            phone: $d['phone'] ?? null,
            vehiclePlate: $d['vehicle_plate'] ?? null,
            documentNumber: $d['document_number'] ?? null,
            vehicleType: $d['vehicle_type'] ?? null,
            capacityKg: isset($d['capacity_kg']) ? (float) $d['capacity_kg'] : null,
            capacityPackages: isset($d['capacity_packages']) ? (int) $d['capacity_packages'] : null,
            email: $d['email'] ?? null,
        );
    }
}
