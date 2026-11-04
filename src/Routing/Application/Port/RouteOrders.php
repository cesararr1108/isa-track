<?php

declare(strict_types=1);

namespace Isa\Routing\Application\Port;

use Isa\Shared\Domain\ValueObject\CompanyId;

/** Puerto hacia Pedidos: leer pedidos y asignarlos al mensajero de la ruta. */
interface RouteOrders
{
    /**
     * @param  list<string>  $orderIds
     * @return array<string, RouteOrder> indexado por id (los que no existen no aparecen)
     */
    public function find(CompanyId $companyId, array $orderIds): array;

    /**
     * Pedidos que se pueden programar: estado creado, alistado, asignado o novedad, y con coordenadas.
     *
     * @return list<RouteOrder>
     */
    public function routable(CompanyId $companyId, int $limit): array;

    /** Asigna el pedido al mensajero (si no lo tiene ya). */
    public function assign(string $orderId, string $carrierId, ?string $byUserId): void;
}
