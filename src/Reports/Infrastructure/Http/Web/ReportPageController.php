<?php

declare(strict_types=1);

namespace Isa\Reports\Infrastructure\Http\Web;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Reports\Application\GenerateReport\GenerateReportHandler;
use Isa\Reports\Infrastructure\Http\ReportCsv;
use Isa\Shared\Domain\Clock;
use Isa\Zones\Application\ListZones\ListZonesHandler;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes por rango de fechas en el panel, con descarga en CSV para Excel. */
final class ReportPageController
{
    public function __construct(private readonly Clock $clock) {}

    public function index(Request $request, GenerateReportHandler $report, ListCarriersHandler $carriers, ListZonesHandler $zones): Response
    {
        [$from, $to, $filters] = $this->input($request);

        return Inertia::render('reports/pages/ReportsIndex', [
            'report' => $report($from, $to, $filters),
            'filters' => ['from' => $from, 'to' => $to] + $filters,
            'today' => $this->clock->now()->format('Y-m-d'),
            'carriers' => array_map(fn (array $c) => ['id' => $c['id'], 'name' => $c['name']], $carriers()),
            'zones' => array_map(fn (array $z) => ['id' => $z['id'], 'name' => $z['name'], 'color' => $z['color']], $zones()),
            'statuses' => config('isa.statuses'),
            'paymentMethods' => config('isa.payment_methods'),
            'incidentReasons' => config('isa.incident_reasons'),
        ]);
    }

    public function csv(Request $request, GenerateReportHandler $report): StreamedResponse
    {
        [$from, $to, $filters] = $this->input($request);

        return ReportCsv::download($report, $from, $to, $filters);
    }

    /** @return array{0: string, 1: string, 2: array{carrier_id: string|null, zone_id: string|null}} */
    private function input(Request $request): array
    {
        $d = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'carrier_id' => ['nullable', 'uuid'],
            'zone_id' => ['nullable', 'uuid'],
        ], ['to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.']);

        return [
            $d['from'] ?? $this->clock->now()->modify('-6 days')->format('Y-m-d'),
            $d['to'] ?? $this->clock->now()->format('Y-m-d'),
            ['carrier_id' => $d['carrier_id'] ?? null, 'zone_id' => $d['zone_id'] ?? null],
        ];
    }
}
