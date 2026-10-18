<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use InvalidArgumentException;

/** Código público de rastreo (va en el link y en la etiqueta). */
final class TrackingCode implements \Stringable
{
    private readonly string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));
        if (! preg_match('/^[A-Z0-9][A-Z0-9-]{5,23}$/', $value)) {
            throw new InvalidArgumentException(sprintf('<%s> no es un código de rastreo válido.', $value));
        }
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
