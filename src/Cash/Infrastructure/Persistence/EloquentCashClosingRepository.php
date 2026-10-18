<?php

declare(strict_types=1);

namespace Isa\Cash\Infrastructure\Persistence;

use Isa\Cash\Domain\BusinessDate;
use Isa\Cash\Domain\CashClosing;
use Isa\Cash\Domain\CashClosingId;
use Isa\Cash\Domain\CashClosingRepository;
use Isa\Cash\Domain\CollectionSummary;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\Money;
use Isa\Shared\Domain\ValueObject\UserId;

final class EloquentCashClosingRepository implements CashClosingRepository
{
    public function save(CashClosing $closing): void
    {
        CashClosingModel::query()->updateOrCreate(['id' => $closing->id()->value()], [
            'company_id' => $closing->companyId()->value(),
            'carrier_id' => $closing->carrierId()->value(),
            'business_date' => $closing->date()->value,
            'expected_by_method' => $closing->expected()->totalsByMethod,
            'orders_count' => $closing->expected()->ordersCount,
            'cash_received' => $closing->cashReceived()->amount,
            'difference' => $closing->difference(),
            'notes' => $closing->notes(),
            'closed_by' => $closing->closedBy()?->value(),
            'closed_at' => $closing->closedAt(),
        ]);
    }

    public function findFor(CompanyId $companyId, CarrierId $carrierId, BusinessDate $date): ?CashClosing
    {
        $row = CashClosingModel::query()
            ->where('company_id', $companyId->value())
            ->where('carrier_id', $carrierId->value())
            ->whereDate('business_date', $date->value)
            ->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function listFor(CompanyId $companyId, BusinessDate $date): array
    {
        return CashClosingModel::query()
            ->where('company_id', $companyId->value())
            ->whereDate('business_date', $date->value)
            ->orderBy('closed_at')
            ->get()
            ->map(fn (CashClosingModel $row) => $this->toDomain($row))
            ->all();
    }

    private function toDomain(CashClosingModel $row): CashClosing
    {
        return CashClosing::restore(
            CashClosingId::fromString($row->id),
            CompanyId::fromString($row->company_id),
            CarrierId::fromString($row->carrier_id),
            new BusinessDate(substr((string) $row->business_date, 0, 10)),
            new CollectionSummary(array_map('intval', $row->expected_by_method), $row->orders_count),
            new Money($row->cash_received),
            $row->notes,
            $row->closed_by !== null ? UserId::fromString($row->closed_by) : null,
            $row->closed_at,
        );
    }
}
