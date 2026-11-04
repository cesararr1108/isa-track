<?php

declare(strict_types=1);

namespace Isa\Fleet\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Isa\Shared\Domain\AggregateRoot;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;

/**
 * Transportador al que se le asignan pedidos: un mensajero propio o una
 * transportadora externa. Guarda los datos del mensajero (cédula, teléfono),
 * su vehículo (placa, tipo, capacidad) y su foto.
 */
final class Carrier extends AggregateRoot
{
    private function __construct(
        private readonly CarrierId $id,
        private readonly CompanyId $companyId,
        private string $name,
        private CarrierType $type,
        private CarrierProfile $profile,
        private ?string $photoPath,
        private bool $active,
        private ?GeoPoint $lastLocation = null,
        private ?DateTimeImmutable $lastLocationAt = null,
    ) {
        $this->guardName($name);
    }

    public static function register(
        CarrierId $id,
        CompanyId $companyId,
        string $name,
        CarrierType $type,
        CarrierProfile $profile = new CarrierProfile,
    ): self {
        return new self($id, $companyId, trim($name), $type, $profile, null, true);
    }

    public static function restore(
        CarrierId $id,
        CompanyId $companyId,
        string $name,
        CarrierType $type,
        CarrierProfile $profile,
        ?string $photoPath,
        bool $active,
        ?GeoPoint $lastLocation = null,
        ?DateTimeImmutable $lastLocationAt = null,
    ): self {
        return new self($id, $companyId, $name, $type, $profile, $photoPath, $active, $lastLocation, $lastLocationAt);
    }

    public function update(string $name, CarrierType $type, CarrierProfile $profile): void
    {
        $this->guardName($name);
        $this->name = trim($name);
        $this->type = $type;
        $this->profile = $profile;
    }

    /** Cambia la foto y devuelve la anterior (para borrarla del disco). */
    public function changePhoto(string $path): ?string
    {
        $previous = $this->photoPath;
        $this->photoPath = $path;

        return $previous;
    }

    /** El mensajero reporta dónde está (app del mensajero). Se ignoran reportes más viejos que el último. */
    public function reportLocation(GeoPoint $location, DateTimeImmutable $at): void
    {
        if ($this->lastLocationAt !== null && $at < $this->lastLocationAt) {
            return;
        }
        $this->lastLocation = $location;
        $this->lastLocationAt = $at;
    }

    public function lastLocation(): ?GeoPoint
    {
        return $this->lastLocation;
    }

    public function lastLocationAt(): ?DateTimeImmutable
    {
        return $this->lastLocationAt;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    /** ¿Puede llevar esta carga en un solo recorrido? */
    public function canCarry(float $weightKg, int $packages): bool
    {
        return $this->profile->capacity->allows($weightKg, $packages);
    }

    private function guardName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('El nombre del transportador es obligatorio.');
        }
    }

    public function id(): CarrierId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): CarrierType
    {
        return $this->type;
    }

    public function profile(): CarrierProfile
    {
        return $this->profile;
    }

    public function phone(): ?string
    {
        return $this->profile->phone;
    }

    public function vehiclePlate(): ?string
    {
        return $this->profile->vehiclePlate;
    }

    public function photoPath(): ?string
    {
        return $this->photoPath;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
