<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\Port;

use Isa\Shared\Domain\ValueObject\CompanyId;

/**
 * Lado de lectura (listados). Para listar no hace falta reconstruir agregados
 * completos, así que se consulta directo a la base con una proyección simple.
 */
interface OrderQueries
{
    /**
     * @param  array{status?: ?string, carrier_id?: ?string, search?: ?string}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    public function search(CompanyId $companyId, array $filters, int $page, int $perPage): array;
}
