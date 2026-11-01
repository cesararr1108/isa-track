<?php

declare(strict_types=1);

namespace Isa\Reports\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Isa\Reports\Application\Port\ReportData;
use Isa\Reports\Application\Port\ReportIncidentRow;
use Isa\Reports\Application\Port\ReportOrderRow;
use Isa\Reports\Application\Port\ReportPaymentRow;
use Isa\Reports\Domain\DateRange;
use Isa\Shared\Domain\ValueObject\CompanyId;

/**
 * Consultas de solo lectura para reportes. Trae filas planas y deja la
 * agregación al caso de uso (así funciona igual en PostgreSQL y SQLite).
 * Los días se cuentan en la zona horaria de la aplicación (America/Bogota).
 */
final class EloquentReportData implements ReportData
{
    public function __construct(private readonly string $timezone) {}

    public function orders(CompanyId $companyId, DateRange $range, array $filters): array
    {
        [$start, $end] = $this->bounds($range);

        return $this->filtered(DB::table('orders'), $companyId, $filters)
            ->whereBetween('orders.created_at', [$start, $end])
            ->orderBy('orders.created_at')
            ->get([
                'orders.id', 'orders.tracking_code', 'orders.created_at', 'orders.status', 'orders.carrier_id',
                'orders.zone_id', 'orders.neighborhood', 'orders.recipient_name', 'orders.delivery_fee',
                'orders.cash_on_delivery', 'orders.delivered_at', 'orders.promised_at',
            ])
            ->map(fn ($o) => new ReportOrderRow(
                id: $o->id,
                trackingCode: $o->tracking_code,
                createdOn: $this->localDay($o->created_at),
                status: $o->status,
                carrierId: $o->carrier_id,
                zoneId: $o->zone_id,
                neighborhood: $o->neighborhood,
                recipientName: $o->recipient_name,
                deliveryFee: (int) ($o->delivery_fee ?? 0),
                cashOnDelivery: (int) ($o->cash_on_delivery ?? 0),
                deliveredOn: $o->delivered_at !== null ? $this->localDay($o->delivered_at) : null,
                onTime: $o->delivered_at !== null && $o->promised_at !== null
                    ? CarbonImmutable::parse($o->delivered_at) <= CarbonImmutable::parse($o->promised_at)
                    : null,
            ))
            ->all();
    }

    public function payments(CompanyId $companyId, DateRange $range, array $filters): array
    {
        [$start, $end] = $this->bounds($range);

        return DB::table('order_payments')
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('order_payments.company_id', $companyId->value())
            ->when($filters['carrier_id'] ?? null, fn ($q, $c) => $q->where('order_payments.carrier_id', $c))
            ->when($filters['zone_id'] ?? null, fn ($q, $z) => $q->where('orders.zone_id', $z))
            ->whereBetween('order_payments.collected_at', [$start, $end])
            ->get(['order_payments.carrier_id', 'order_payments.method', 'order_payments.amount', 'order_payments.collected_at'])
            ->map(fn ($p) => new ReportPaymentRow($p->carrier_id, $p->method, (int) $p->amount, $this->localDay($p->collected_at)))
            ->all();
    }

    public function incidents(CompanyId $companyId, DateRange $range, array $filters): array
    {
        [$start, $end] = $this->bounds($range);

        return $this->filtered(DB::table('order_status_history')->join('orders', 'orders.id', '=', 'order_status_history.order_id'), $companyId, $filters)
            ->where('order_status_history.status', 'incident')
            ->whereBetween('order_status_history.occurred_at', [$start, $end])
            ->get(['order_status_history.incident_reason', 'orders.carrier_id', 'order_status_history.occurred_at'])
            ->map(fn ($i) => new ReportIncidentRow($i->incident_reason ?? 'other', $i->carrier_id, $this->localDay($i->occurred_at)))
            ->all();
    }

    public function carrierNames(CompanyId $companyId): array
    {
        return DB::table('carriers')->where('company_id', $companyId->value())->pluck('name', 'id')->all();
    }

    public function zoneNames(CompanyId $companyId): array
    {
        return DB::table('zones')->where('company_id', $companyId->value())->pluck('name', 'id')->all();
    }

    private function filtered(Builder $query, CompanyId $companyId, array $filters): Builder
    {
        return $query
            ->where('orders.company_id', $companyId->value())
            ->when($filters['carrier_id'] ?? null, fn ($q, $c) => $q->where('orders.carrier_id', $c))
            ->when($filters['zone_id'] ?? null, fn ($q, $z) => $q->where('orders.zone_id', $z));
    }

    /**
     * Inicio y fin del rango en hora local. Las fechas se guardan en la zona de la
     * aplicación (y PostgreSQL usa esa misma zona en la conexión, config/database.php).
     *
     * @return array{0: string, 1: string}
     */
    private function bounds(DateRange $range): array
    {
        return [$range->from->format('Y-m-d').' 00:00:00', $range->to->format('Y-m-d').' 23:59:59'];
    }

    private function localDay(string $stored): string
    {
        return CarbonImmutable::parse($stored, $this->timezone)->setTimezone($this->timezone)->format('Y-m-d');
    }
}
