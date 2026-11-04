<?php

declare(strict_types=1);

namespace Isa\Routing\Application\ListRoutableOrders;

use Isa\Routing\Application\Port\RouteOrder;
use Isa\Routing\Application\Port\RouteOrders;
use Isa\Routing\Domain\RouteRepository;
use Isa\Shared\Application\TenantContext;

/** Pedidos disponibles para programar en una ruta nueva (no están en otra ruta abierta). */
final class ListRoutableOrdersHandler
{
    private const LIMIT = 300;

    public function __construct(
        private readonly RouteOrders $orders,
        private readonly RouteRepository $routes,
        private readonly TenantContext $tenant,
    ) {}

    /** @return list<array<string, mixed>> */
    public function __invoke(): array
    {
        $companyId = $this->tenant->companyId();
        $orders = $this->orders->routable($companyId, self::LIMIT);
        $busy = array_flip($this->routes->ordersInOpenRoutes($companyId, array_map(fn (RouteOrder $o) => $o->id, $orders)));

        return array_values(array_map(fn (RouteOrder $o) => [
            'id' => $o->id,
            'tracking_code' => $o->trackingCode,
            'status' => $o->status,
            'recipient_name' => $o->recipientName,
            'address' => $o->addressLine,
            'neighborhood' => $o->neighborhood,
            'latitude' => $o->latitude,
            'longitude' => $o->longitude,
            'weight_kg' => round($o->weightKg, 2),
            'packages' => $o->packages,
            'carrier_id' => $o->carrierId,
        ], array_filter($orders, fn (RouteOrder $o) => ! isset($busy[$o->id]))));
    }
}
