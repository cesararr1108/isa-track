<?php

declare(strict_types=1);

namespace Isa\Cash\Infrastructure\Adapter;

use Carbon\CarbonImmutable;
use Isa\Cash\Application\Port\CollectedPayments;
use Isa\Cash\Domain\BusinessDate;
use Isa\Cash\Domain\CollectionSummary;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shipping\Infrastructure\Persistence\PaymentModel;

/** Suma los pagos registrados en las entregas (tabla order_payments) para un día. */
final class ShippingCollectedPayments implements CollectedPayments
{
    public function __construct(private readonly string $timezone) {}

    public function summaryFor(CompanyId $companyId, CarrierId $carrierId, BusinessDate $date): CollectionSummary
    {
        $start = CarbonImmutable::parse($date->value, $this->timezone)->startOfDay();

        $rows = PaymentModel::query()
            ->where('company_id', $companyId->value())
            ->where('carrier_id', $carrierId->value())
            ->where('collected_at', '>=', $start)
            ->where('collected_at', '<', $start->addDay())
            ->selectRaw('method, SUM(amount) AS total')
            ->groupBy('method')
            ->get();

        $orders = PaymentModel::query()
            ->where('company_id', $companyId->value())
            ->where('carrier_id', $carrierId->value())
            ->where('collected_at', '>=', $start)
            ->where('collected_at', '<', $start->addDay())
            ->distinct()
            ->count('order_id');

        return new CollectionSummary(
            $rows->mapWithKeys(fn ($r) => [$r->method => (int) $r->total])->all(),
            $orders,
        );
    }
}
