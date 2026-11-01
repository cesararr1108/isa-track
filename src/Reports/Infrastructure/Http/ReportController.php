<?php

declare(strict_types=1);

namespace Isa\Reports\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Isa\Reports\Application\GenerateReport\GenerateReportHandler;
use Isa\Reports\Application\Port\ReportOrderRow;
use Isa\Shared\Domain\Clock;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes por rango de fechas (JSON para el panel y CSV para Excel). */
final class ReportController
{
    public function __construct(private readonly Clock $clock) {}

    /** GET /api/reports/summary?from=&to=&carrier_id=&zone_id= */
    public function summary(Request $request, GenerateReportHandler $report): JsonResponse
    {
        [$from, $to, $filters] = $this->input($request);

        return new JsonResponse(['data' => $report($from, $to, $filters)]);
    }

    /** GET /api/reports/orders.csv?from=&to=  (se abre directo en Excel) */
    public function ordersCsv(Request $request, GenerateReportHandler $report): StreamedResponse
    {
        [$from, $to, $filters] = $this->input($request);
        $rows = $report->rows($from, $to, $filters);
        $carriers = $report->carrierNames();
        $zones = $report->zoneNames();
        $statuses = config('isa.statuses');

        return new StreamedResponse(function () use ($rows, $carriers, $zones, $statuses): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel reconoce tildes y eñes.
            fputcsv($out, ['Código', 'Creado', 'Estado', 'Destinatario', 'Barrio', 'Zona', 'Mensajero', 'Tarifa domicilio', 'Contra entrega', 'Entregado', 'A tiempo'], ';');
            foreach ($rows as $r) {
                /** @var ReportOrderRow $r */
                fputcsv($out, [
                    $r->trackingCode,
                    $r->createdOn,
                    $statuses[$r->status]['label'] ?? $r->status,
                    $r->recipientName,
                    $r->neighborhood,
                    $r->zoneId !== null ? ($zones[$r->zoneId] ?? '') : 'Fuera de zona',
                    $r->carrierId !== null ? ($carriers[$r->carrierId] ?? '') : 'Sin asignar',
                    $r->deliveryFee,
                    $r->cashOnDelivery,
                    $r->deliveredOn,
                    $r->onTime === null ? '' : ($r->onTime ? 'Sí' : 'No'),
                ], ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="pedidos_%s_%s.csv"', $from, $to),
        ]);
    }

    /** @return array{0: string, 1: string, 2: array{carrier_id: string|null, zone_id: string|null}} */
    private function input(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'carrier_id' => ['nullable', 'uuid'],
            'zone_id' => ['nullable', 'uuid'],
        ]);
        $today = $this->clock->now()->format('Y-m-d');

        return [
            $data['from'] ?? $this->clock->now()->modify('-6 days')->format('Y-m-d'),
            $data['to'] ?? $today,
            ['carrier_id' => $data['carrier_id'] ?? null, 'zone_id' => $data['zone_id'] ?? null],
        ];
    }
}
