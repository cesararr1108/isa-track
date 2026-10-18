<?php

declare(strict_types=1);

namespace Isa\Cash\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/** Día de operación (Y-m-d) en la zona horaria de la empresa. */
final class BusinessDate implements \Stringable
{
    public function __construct(public readonly string $value)
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(sprintf('<%s> no es una fecha válida (AAAA-MM-DD).', $value));
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
