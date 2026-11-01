<?php

declare(strict_types=1);

namespace Isa\Reports\Application\GenerateReport;

use Isa\Reports\Application\Port\ReportData;
use Isa\Reports\Application\Port\ReportIncidentRow;
use Isa\Reports\Application\Port\ReportOrderRow;
use Isa\Reports\Application\Port\ReportPaymentRow;
use Isa\Reports\Domain\DateRange;
use Isa\Shared\Application\TenantContext;

/**
 * Reporte de operación por rango de fechas: totales, por día, por mensajero,
 * por zona, recaudo por medio de pago y novedades por motivo.
 *
 * - Estados, tarifas y zonas: pedidos CREADOS en el rango.
 * - Recaudo: pagos COBRADOS en el rango.
 * - Novedades: novedades REGISTRADAS en el rango.
 */
final class GenerateReportHandler
{
    private const ACTIVE = ['created', 'prepared', 'assigned', 'in_route'];

    public function __construct(
        private readonly ReportData $data,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{carrier_id?: string|null, zone_id?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function __invoke(string $from, string $to, array $filters = []): array
    {
        $companyId = $this->tenant->companyId();
        $range = new DateRange($from, $to);

        $orders = $this->data->orders($companyId, $range, $filters);
        $payments = $this->data->payments($companyId, $range, $filters);
        $incidents = $this->data->incidents($companyId, $range, $filters);
        $carrierNames = $this->data->carrierNames($companyId);
        $zoneNames = $this->data->zoneNames($companyId);

        return [
            'range' => ['from' => $from, 'to' => $to, 'days' => $range->days()],
            'filters' => array_filter($filters),
            'totals' => $this->totals($orders, $payments, $incidents),
            'by_day' => $this->byDay($range, $orders, $payments),
            'by_carrier' => $this->byCarrier($orders, $payments, $incidents, $carrierNames),
            'by_zone' => $this->byZone($orders, $zoneNames),
            'collections_by_method' => $this->byMethod($payments),
            'incidents_by_reason' => $this->countBy($incidents, fn (ReportIncidentRow $i) => $i->reason),
        ];
    }

    /** @return list<ReportOrderRow> pedidos del rango (para exportar a CSV/Excel) */
    public function rows(string $from, string $to, array $filters = []): array
    {
        return $this->data->orders($this->tenant->companyId(), new DateRange($from, $to), $filters);
    }

    /** @return array<string, string> */
    public function carrierNames(): array
    {
        return $this->data->carrierNames($this->tenant->companyId());
    }

    /** @return array<string, string> */
    public function zoneNames(): array
    {
        return $this->data->zoneNames($this->tenant->companyId());
    }

    /**
     * @param  list<ReportOrderRow>  $orders
     * @param  list<ReportPaymentRow>  $payments
     * @param  list<ReportIncidentRow>  $incidents
     * @return array<string, mixed>
     */
    private function totals(array $orders, array $payments, array $incidents): array
    {
        $byStatus = $this->countBy($orders, fn (ReportOrderRow $o) => $o->status);
        $delivered = $byStatus['delivered'] ?? 0;
        $closed = $delivered + ($byStatus['returned'] ?? 0);
        $withPromise = array_filter($orders, fn (ReportOrderRow $o) => $o->onTime !== null);
        $onTime = count(array_filter($withPromise, fn (ReportOrderRow $o) => $o->onTime === true));

        return [
            'orders' => count($orders),
            'by_status' => $byStatus,
            'delivered' => $delivered,
            'returned' => $byStatus['returned'] ?? 0,
            'in_progress' => array_sum(array_map(fn ($s) => $byStatus[$s] ?? 0, self::ACTIVE)),
            'with_incident_now' => $byStatus['incident'] ?? 0,
            'incidents_reported' => count($incidents),
            'delivery_rate' => $closed === 0 ? null : round($delivered * 100 / $closed, 1),
            'on_time_rate' => $withPromise === [] ? null : round($onTime * 100 / count($withPromise), 1),
            'delivery_fees' => array_sum(array_map(fn (ReportOrderRow $o) => $o->deliveryFee, $orders)),
            'delivery_fees_delivered' => array_sum(array_map(fn (ReportOrderRow $o) => $o->status === 'delivered' ? $o->deliveryFee : 0, $orders)),
            'collected' => array_sum(array_map(fn (ReportPaymentRow $p) => $p->amount, $payments)),
            'collected_cash' => array_sum(array_map(fn (ReportPaymentRow $p) => $p->method === 'cash' ? $p->amount : 0, $payments)),
        ];
    }

    /**
     * @param  list<ReportOrderRow>  $orders
     * @param  list<ReportPaymentRow>  $payments
     * @return list<array<string, mixed>>
     */
    private function byDay(DateRange $range, array $orders, array $payments): array
    {
        $days = [];
        foreach ($range->eachDay() as $day) {
            $days[$day] = ['date' => $day, 'created' => 0, 'delivered' => 0, 'delivery_fees' => 0, 'collected' => 0];
        }
        foreach ($orders as $o) {
            if (isset($days[$o->createdOn])) {
                $days[$o->createdOn]['created']++;
                $days[$o->createdOn]['delivery_fees'] += $o->deliveryFee;
            }
            if ($o->deliveredOn !== null && isset($days[$o->deliveredOn])) {
                $days[$o->deliveredOn]['delivered']++;
            }
        }
        foreach ($payments as $p) {
            if (isset($days[$p->collectedOn])) {
                $days[$p->collectedOn]['collected'] += $p->amount;
            }
        }

        return array_values($days);
    }

    /**
     * @param  list<ReportOrderRow>  $orders
     * @param  list<ReportPaymentRow>  $payments
     * @param  list<ReportIncidentRow>  $incidents
     * @param  array<string, string>  $names
     * @return list<array<string, mixed>>
     */
    private function byCarrier(array $orders, array $payments, array $incidents, array $names): array
    {
        $rows = [];
        $row = function (?string $id) use (&$rows, $names): string {
            $key = $id ?? 'none';
            $rows[$key] ??= [
                'carrier_id' => $id,
                'name' => $id === null ? 'Sin asignar' : ($names[$id] ?? 'Transportador eliminado'),
                'orders' => 0, 'delivered' => 0, 'returned' => 0, 'incidents' => 0,
                'delivery_fees' => 0, 'collected' => 0, 'collected_cash' => 0,
            ];

            return $key;
        };
        foreach ($orders as $o) {
            $k = $row($o->carrierId);
            $rows[$k]['orders']++;
            $rows[$k]['delivered'] += $o->status === 'delivered' ? 1 : 0;
            $rows[$k]['returned'] += $o->status === 'returned' ? 1 : 0;
            $rows[$k]['delivery_fees'] += $o->deliveryFee;
        }
        foreach ($incidents as $i) {
            $rows[$row($i->carrierId)]['incidents']++;
        }
        foreach ($payments as $p) {
            $k = $row($p->carrierId);
            $rows[$k]['collected'] += $p->amount;
            $rows[$k]['collected_cash'] += $p->method === 'cash' ? $p->amount : 0;
        }
        foreach ($rows as &$r) {
            $closed = $r['delivered'] + $r['returned'];
            $r['delivery_rate'] = $closed === 0 ? null : round($r['delivered'] * 100 / $closed, 1);
        }
        unset($r);
        usort($rows, fn ($a, $b) => [$b['delivered'], $a['name']] <=> [$a['delivered'], $b['name']]);

        return $rows;
    }

    /**
     * @param  list<ReportOrderRow>  $orders
     * @param  array<string, string>  $names
     * @return list<array<string, mixed>>
     */
    private function byZone(array $orders, array $names): array
    {
        $rows = [];
        foreach ($orders as $o) {
            $key = $o->zoneId ?? 'none';
            $rows[$key] ??= [
                'zone_id' => $o->zoneId,
                'name' => $o->zoneId === null ? 'Fuera de zona' : ($names[$o->zoneId] ?? 'Zona eliminada'),
                'orders' => 0, 'delivered' => 0, 'delivery_fees' => 0,
            ];
            $rows[$key]['orders']++;
            $rows[$key]['delivered'] += $o->status === 'delivered' ? 1 : 0;
            $rows[$key]['delivery_fees'] += $o->deliveryFee;
        }
        usort($rows, fn ($a, $b) => [$b['orders'], $a['name']] <=> [$a['orders'], $b['name']]);

        return $rows;
    }

    /**
     * @param  list<ReportPaymentRow>  $payments
     * @return array<string, int>
     */
    private function byMethod(array $payments): array
    {
        $out = [];
        foreach ($payments as $p) {
            $out[$p->method] = ($out[$p->method] ?? 0) + $p->amount;
        }
        arsort($out);

        return $out;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @param  callable(T): string  $key
     * @return array<string, int>
     */
    private function countBy(array $items, callable $key): array
    {
        $out = [];
        foreach ($items as $item) {
            $k = $key($item);
            $out[$k] = ($out[$k] ?? 0) + 1;
        }
        arsort($out);

        return $out;
    }
}
