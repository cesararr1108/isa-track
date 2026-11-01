<?php

declare(strict_types=1);

namespace Isa\Zones\Domain;

use InvalidArgumentException;
use Isa\Shared\Domain\AggregateRoot;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shared\Domain\ValueObject\Money;

/**
 * Zona de cobertura con su tarifa de domicilio (p. ej. "El Poblado: $8.000").
 * El área es un polígono dibujado en el mapa.
 */
final class Zone extends AggregateRoot
{
    private function __construct(
        private readonly ZoneId $id,
        private readonly CompanyId $companyId,
        private string $name,
        private string $color,
        private Polygon $area,
        private Money $deliveryFee,
        private ?int $estimatedMinutes,
        private bool $active,
    ) {
        $this->guard($name, $color, $estimatedMinutes);
    }

    public static function create(
        ZoneId $id,
        CompanyId $companyId,
        string $name,
        string $color,
        Polygon $area,
        Money $deliveryFee,
        ?int $estimatedMinutes = null,
    ): self {
        return new self($id, $companyId, trim($name), strtoupper($color), $area, $deliveryFee, $estimatedMinutes, true);
    }

    public static function restore(
        ZoneId $id,
        CompanyId $companyId,
        string $name,
        string $color,
        Polygon $area,
        Money $deliveryFee,
        ?int $estimatedMinutes,
        bool $active,
    ): self {
        return new self($id, $companyId, $name, $color, $area, $deliveryFee, $estimatedMinutes, $active);
    }

    public function update(string $name, string $color, Polygon $area, Money $deliveryFee, ?int $estimatedMinutes, bool $active): void
    {
        $this->guard($name, $color, $estimatedMinutes);
        $this->name = trim($name);
        $this->color = strtoupper($color);
        $this->area = $area;
        $this->deliveryFee = $deliveryFee;
        $this->estimatedMinutes = $estimatedMinutes;
        $this->active = $active;
    }

    public function covers(GeoPoint $point): bool
    {
        return $this->active && $this->area->contains($point);
    }

    private function guard(string $name, string $color, ?int $estimatedMinutes): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('La zona necesita un nombre.');
        }
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new InvalidArgumentException('El color de la zona debe ser hexadecimal, p. ej. #00C896.');
        }
        if ($estimatedMinutes !== null && $estimatedMinutes <= 0) {
            throw new InvalidArgumentException('El tiempo estimado debe ser mayor que cero.');
        }
    }

    public function id(): ZoneId
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

    public function color(): string
    {
        return $this->color;
    }

    public function area(): Polygon
    {
        return $this->area;
    }

    public function deliveryFee(): Money
    {
        return $this->deliveryFee;
    }

    public function estimatedMinutes(): ?int
    {
        return $this->estimatedMinutes;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
