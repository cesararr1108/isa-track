<?php

declare(strict_types=1);

namespace Isa\Shared\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Dinero en pesos colombianos, como entero (el COP no usa centavos en la práctica).
 * Nunca usar float para dinero.
 */
final class Money
{
    public function __construct(public readonly int $amount)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Un valor de dinero no puede ser negativo.');
        }
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }
}
