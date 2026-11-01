<?php

declare(strict_types=1);

namespace Isa\Fleet\Domain;

use InvalidArgumentException;

/**
 * Datos del mensajero y su vehículo: cédula, teléfono, placa, tipo de vehículo,
 * capacidad y correo. Normaliza la placa (ABC12D) y la cédula (solo dígitos).
 */
final class CarrierProfile
{
    public readonly ?string $documentNumber;

    public readonly ?string $vehiclePlate;

    public function __construct(
        ?string $documentNumber = null,
        public readonly ?string $phone = null,
        ?string $vehiclePlate = null,
        public readonly ?VehicleType $vehicleType = null,
        public readonly VehicleCapacity $capacity = new VehicleCapacity,
        public readonly ?string $email = null,
    ) {
        $document = $documentNumber === null ? null : preg_replace('/[^0-9]/', '', $documentNumber);
        if ($document === '') {
            $document = null;
        }
        if ($document !== null && (strlen($document) < 5 || strlen($document) > 15)) {
            throw new InvalidArgumentException('La cédula debe tener entre 5 y 15 dígitos.');
        }

        $plate = $vehiclePlate === null ? null : strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $vehiclePlate));
        if ($plate === '') {
            $plate = null;
        }
        if ($plate !== null && ! preg_match('/^[A-Z0-9]{5,7}$/', $plate)) {
            throw new InvalidArgumentException('La placa debe tener entre 5 y 7 letras o números (ej.: ABC12D).');
        }

        if ($phone !== null && ! preg_match('/^\+?[0-9 ]{7,20}$/', $phone)) {
            throw new InvalidArgumentException('El teléfono del mensajero no es válido.');
        }

        $this->documentNumber = $document;
        $this->vehiclePlate = $plate;
    }
}
