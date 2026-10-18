<?php

declare(strict_types=1);

namespace Isa\Shared\Domain\ValueObject;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid as RamseyUuid;

/**
 * Identificador UUID inmutable. Cada entidad tiene su propia subclase
 * (OrderId, CarrierId...) para que el tipado evite mezclar identificadores.
 */
abstract class Uuid implements \Stringable
{
    final public function __construct(private readonly string $value)
    {
        if (! RamseyUuid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('<%s> no es un UUID válido.', $value));
        }
    }

    public static function generate(): static
    {
        return new static(RamseyUuid::uuid7()->toString());
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
