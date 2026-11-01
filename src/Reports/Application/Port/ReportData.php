<?php

declare(strict_types=1);

namespace Isa\Reports\Application\Port;

use Isa\Reports\Domain\DateRange;
use Isa\Shared\Domain\ValueObject\CompanyId;

/**
 * Fuente de datos de los reportes (consulta de solo lectura sobre Pedidos, Caja,
 * Flota y Zonas). Filtros opcionales: carrier_id, zone_id.
 */
interface ReportData
{
    /**
     * Pedidos creados en el rango.
     *
     * @param  array{carrier_id?: string|null, zone_id?: string|null}  $filters
     * @return list<ReportOrderRow>
     */
    public function orders(CompanyId $companyId, DateRange $range, array $filters): array;

    /**
     * Pagos recaudados en el rango.
     *
     * @param  array{carrier_id?: string|null, zone_id?: string|null}  $filters
     * @return list<ReportPaymentRow>
     */
    public function payments(CompanyId $companyId, DateRange $range, array $filters): array;

    /**
     * Novedades registradas en el rango.
     *
     * @param  array{carrier_id?: string|null, zone_id?: string|null}  $filters
     * @return list<ReportIncidentRow>
     */
    public function incidents(CompanyId $companyId, DateRange $range, array $filters): array;

    /** @return array<string, string> id => nombre */
    public function carrierNames(CompanyId $companyId): array;

    /** @return array<string, string> id => nombre */
    public function zoneNames(CompanyId $companyId): array;
}
