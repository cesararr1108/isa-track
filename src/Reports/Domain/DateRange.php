<?php

declare(strict_types=1);

namespace Isa\Reports\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/** Rango de fechas de un reporte (ambos días incluidos, máximo un año). */
final class DateRange
{
    public const MAX_DAYS = 366;

    public readonly DateTimeImmutable $from;

    public readonly DateTimeImmutable $to;

    public function __construct(string $from, string $to)
    {
        $this->from = self::parse($from);
        $this->to = self::parse($to);
        if ($this->to < $this->from) {
            throw new InvalidArgumentException('La fecha final no puede ser anterior a la inicial.');
        }
        if ($this->days() > self::MAX_DAYS) {
            throw new InvalidArgumentException(sprintf('El rango máximo de un reporte es de %d días.', self::MAX_DAYS));
        }
    }

    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    /** @return list<string> cada día del rango en Y-m-d */
    public function eachDay(): array
    {
        $days = [];
        for ($d = $this->from; $d <= $this->to; $d = $d->modify('+1 day')) {
            $days[] = $d->format('Y-m-d');
        }

        return $days;
    }

    private static function parse(string $value): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($d === false || $d->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(sprintf('<%s> no es una fecha válida (AAAA-MM-DD).', $value));
        }

        return $d;
    }
}
