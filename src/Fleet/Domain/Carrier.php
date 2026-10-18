<?php

declare(strict_types=1);

namespace Isa\Fleet\Domain;

use InvalidArgumentException;
use Isa\Shared\Domain\AggregateRoot;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

/** Transportador al que se le asignan pedidos. */
final class Carrier extends AggregateRoot
{
    private function __construct(
        private readonly CarrierId $id,
        private readonly CompanyId $companyId,
        private string $name,
        private readonly CarrierType $type,
        private ?string $phone,
        private ?string $vehiclePlate,
        private bool $active,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('El nombre del transportador es obligatorio.');
        }
    }

    public static function register(
        CarrierId $id,
        CompanyId $companyId,
        string $name,
        CarrierType $type,
        ?string $phone,
        ?string $vehiclePlate,
    ): self {
        return new self($id, $companyId, $name, $type, $phone, $vehiclePlate, true);
    }

    public static function restore(
        CarrierId $id,
        CompanyId $companyId,
        string $name,
        CarrierType $type,
        ?string $phone,
        ?string $vehiclePlate,
        bool $active,
    ): self {
        return new self($id, $companyId, $name, $type, $phone, $vehiclePlate, $active);
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
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

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function vehiclePlate(): ?string
    {
        return $this->vehiclePlate;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
