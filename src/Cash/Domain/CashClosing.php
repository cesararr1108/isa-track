<?php

declare(strict_types=1);

namespace Isa\Cash\Domain;

use DateTimeImmutable;
use Isa\Cash\Domain\Exception\DifferenceRequiresNote;
use Isa\Shared\Domain\AggregateRoot;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\Money;
use Isa\Shared\Domain\ValueObject\UserId;

/**
 * Cuadre de caja de un conductor para un día: compara el efectivo que el
 * sistema esperaba con el que el conductor entregó. Una vez cerrado no cambia.
 */
final class CashClosing extends AggregateRoot
{
    private function __construct(
        private readonly CashClosingId $id,
        private readonly CompanyId $companyId,
        private readonly CarrierId $carrierId,
        private readonly BusinessDate $date,
        private readonly CollectionSummary $expected,
        private readonly Money $cashReceived,
        private readonly ?string $notes,
        private readonly ?UserId $closedBy,
        private readonly DateTimeImmutable $closedAt,
    ) {}

    public static function close(
        CashClosingId $id,
        CompanyId $companyId,
        CarrierId $carrierId,
        BusinessDate $date,
        CollectionSummary $expected,
        Money $cashReceived,
        ?string $notes,
        bool $requireNoteOnDifference,
        ?UserId $closedBy,
        DateTimeImmutable $closedAt,
    ): self {
        $closing = new self($id, $companyId, $carrierId, $date, $expected, $cashReceived, $notes, $closedBy, $closedAt);

        if ($requireNoteOnDifference && $closing->difference() !== 0 && trim((string) $notes) === '') {
            throw new DifferenceRequiresNote($closing->difference());
        }

        return $closing;
    }

    public static function restore(
        CashClosingId $id,
        CompanyId $companyId,
        CarrierId $carrierId,
        BusinessDate $date,
        CollectionSummary $expected,
        Money $cashReceived,
        ?string $notes,
        ?UserId $closedBy,
        DateTimeImmutable $closedAt,
    ): self {
        return new self($id, $companyId, $carrierId, $date, $expected, $cashReceived, $notes, $closedBy, $closedAt);
    }

    /** Positivo = sobrante, negativo = faltante. */
    public function difference(): int
    {
        return $this->cashReceived->amount - $this->expected->expectedCash()->amount;
    }

    public function result(): string
    {
        return match (true) {
            $this->difference() === 0 => 'balanced',
            $this->difference() < 0 => 'short',
            default => 'over',
        };
    }

    public function id(): CashClosingId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function carrierId(): CarrierId
    {
        return $this->carrierId;
    }

    public function date(): BusinessDate
    {
        return $this->date;
    }

    public function expected(): CollectionSummary
    {
        return $this->expected;
    }

    public function cashReceived(): Money
    {
        return $this->cashReceived;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function closedBy(): ?UserId
    {
        return $this->closedBy;
    }

    public function closedAt(): DateTimeImmutable
    {
        return $this->closedAt;
    }
}
