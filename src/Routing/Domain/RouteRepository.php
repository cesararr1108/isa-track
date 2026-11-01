<?php

declare(strict_types=1);

namespace Isa\Routing\Domain;

use Isa\Shared\Domain\ValueObject\CompanyId;

interface RouteRepository
{
    public function save(DeliveryRoute $route): void;

    public function find(CompanyId $companyId, RouteId $id): ?DeliveryRoute;

    /**
     * @param  array{date?: string|null, from?: string|null, to?: string|null, carrier_id?: string|null, status?: string|null}  $filters
     * @return list<DeliveryRoute>
     */
    public function search(CompanyId $companyId, array $filters): array;

    /**
     * Pedidos de la lista que ya están en una ruta abierta (planeada o en curso).
     *
     * @param  list<string>  $orderIds
     * @return list<string>
     */
    public function ordersInOpenRoutes(CompanyId $companyId, array $orderIds, ?RouteId $except = null): array;
}
