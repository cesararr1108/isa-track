<?php

declare(strict_types=1);

namespace Isa\Cash\Domain;

use Isa\Shared\Domain\ValueObject\Money;

/**
 * Lo que el sistema dice que un conductor recaudó en un día,
 * agrupado por medio de pago ('cash', 'nequi', ...).
 */
final class CollectionSummary
{
    public const CASH = 'cash';

    /** @param array<string, int> $totalsByMethod */
    public function __construct(
        public readonly array $totalsByMethod,
        public readonly int $ordersCount,
    ) {}

    /** Efectivo que el conductor debe entregar físicamente. */
    public function expectedCash(): Money
    {
        return new Money($this->totalsByMethod[self::CASH] ?? 0);
    }

    public function total(): Money
    {
        return new Money(array_sum($this->totalsByMethod));
    }
}
