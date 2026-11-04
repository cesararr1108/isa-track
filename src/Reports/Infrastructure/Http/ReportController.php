<?php

declare(strict_types=1);

namespace Isa\Reports\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Isa\Reports\Application\GenerateReport\GenerateReportHandler;
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

        return ReportCsv::download($report, $from, $to, $filters);
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
