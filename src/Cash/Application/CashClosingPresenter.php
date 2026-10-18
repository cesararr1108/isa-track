<?php

declare(strict_types=1);

namespace Isa\Cash\Application;

use Isa\Cash\Domain\CashClosing;
use Isa\Cash\Domain\CollectionSummary;

final class CashClosingPresenter
{
    /** @return array<string, mixed> */
    public static function summary(CollectionSummary $summary): array
    {
        return [
            'orders_count' => $summary->ordersCount,
            'totals_by_method' => $summary->totalsByMethod,
            'total' => $summary->total()->amount,
            'expected_cash' => $summary->expectedCash()->amount,
        ];
    }

    /** @return array<string, mixed> */
    public static function closing(CashClosing $closing): array
    {
        return [
            'id' => $closing->id()->value(),
            'carrier_id' => $closing->carrierId()->value(),
            'date' => $closing->date()->value,
            'expected' => self::summary($closing->expected()),
            'cash_received' => $closing->cashReceived()->amount,
            'difference' => $closing->difference(),
            'result' => $closing->result(),
            'notes' => $closing->notes(),
            'closed_by' => $closing->closedBy()?->value(),
            'closed_at' => $closing->closedAt()->format(DATE_ATOM),
        ];
    }
}
