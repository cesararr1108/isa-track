<?php

declare(strict_types=1);

namespace Isa\Reports\Infrastructure\Http;

use Isa\Reports\Application\GenerateReport\GenerateReportHandler;
use Isa\Reports\Application\Port\ReportOrderRow;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exporta los pedidos de un rango a CSV para Excel (separador ";" y BOM para tildes). */
final class ReportCsv
{
    /** @param array{carrier_id?: string|null, zone_id?: string|null} $filters */
    public static function download(GenerateReportHandler $report, string $from, string $to, array $filters): StreamedResponse
    {
        $rows = $report->rows($from, $to, $filters);
        $carriers = $report->carrierNames();
        $zones = $report->zoneNames();
        $statuses = config('isa.statuses');

        return new StreamedResponse(function () use ($rows, $carriers, $zones, $statuses): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Código', 'Creado', 'Estado', 'Destinatario', 'Barrio', 'Zona', 'Mensajero', 'Tarifa domicilio', 'Contra entrega', 'Entregado', 'A tiempo'], ';');
            foreach ($rows as $r) {
                /** @var ReportOrderRow $r */
                fputcsv($out, [
                    $r->trackingCode,
                    $r->createdOn,
                    $statuses[$r->status]['label'] ?? $r->status,
                    self::safe($r->recipientName),
                    self::safe((string) $r->neighborhood),
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
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Evita "inyección de fórmulas" al abrir en Excel: un texto que empieza por = + - @ se escapa. */
    private static function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
