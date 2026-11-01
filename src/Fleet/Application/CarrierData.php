<?php

declare(strict_types=1);

namespace Isa\Fleet\Application;

use Isa\Fleet\Domain\CarrierProfile;
use Isa\Fleet\Domain\VehicleCapacity;
use Isa\Fleet\Domain\VehicleType;

/** Datos de entrada de un transportador (al registrarlo o editarlo). */
final class CarrierData
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $phone = null,
        public readonly ?string $vehiclePlate = null,
        public readonly ?string $documentNumber = null,
        public readonly ?string $vehicleType = null,
        public readonly ?float $capacityKg = null,
        public readonly ?int $capacityPackages = null,
        public readonly ?string $email = null,
    ) {}

    public function profile(): CarrierProfile
    {
        return new CarrierProfile(
            documentNumber: $this->documentNumber,
            phone: $this->phone,
            vehiclePlate: $this->vehiclePlate,
            vehicleType: $this->vehicleType !== null ? VehicleType::from($this->vehicleType) : null,
            capacity: new VehicleCapacity($this->capacityKg, $this->capacityPackages),
            email: $this->email,
        );
    }
}
