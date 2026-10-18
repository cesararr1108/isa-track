<?php

declare(strict_types=1);

namespace Tests\Unit\Cash;

use DateTimeImmutable;
use InvalidArgumentException;
use Isa\Cash\Domain\BusinessDate;
use Isa\Cash\Domain\CashClosing;
use Isa\Cash\Domain\CashClosingId;
use Isa\Cash\Domain\CollectionSummary;
use Isa\Cash\Domain\Exception\DifferenceRequiresNote;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CashClosingTest extends TestCase
{
    public function test_balanced_closing_only_compares_cash(): void
    {
        $closing = $this->close(cashReceived: 150000);

        self::assertSame(0, $closing->difference());
        self::assertSame('balanced', $closing->result());
        self::assertSame(230000, $closing->expected()->total()->amount, 'Nequi y datáfono se reportan pero no se entregan en efectivo');
    }

    public function test_short_cash_requires_a_note(): void
    {
        $this->expectException(DifferenceRequiresNote::class);

        $this->close(cashReceived: 140000);
    }

    public function test_difference_with_note_is_recorded(): void
    {
        $closing = $this->close(cashReceived: 140000, notes: 'Dio vueltas de más');

        self::assertSame(-10000, $closing->difference());
        self::assertSame('short', $closing->result());
    }

    public function test_note_requirement_can_be_disabled(): void
    {
        $closing = $this->close(cashReceived: 160000, requireNote: false);

        self::assertSame('over', $closing->result());
    }

    public function test_business_date_is_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BusinessDate('2026-02-30');
    }

    private function close(int $cashReceived, ?string $notes = null, bool $requireNote = true): CashClosing
    {
        return CashClosing::close(
            CashClosingId::generate(),
            CompanyId::generate(),
            CarrierId::generate(),
            new BusinessDate('2026-10-18'),
            new CollectionSummary(['cash' => 150000, 'nequi' => 50000, 'card_terminal' => 30000], 4),
            new Money($cashReceived),
            $notes,
            $requireNote,
            null,
            new DateTimeImmutable('2026-10-18 19:00'),
        );
    }
}
