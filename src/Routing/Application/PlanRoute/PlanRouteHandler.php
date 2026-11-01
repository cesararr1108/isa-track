<?php

declare(strict_types=1);

namespace Isa\Routing\Application\PlanRoute;

use Isa\Routing\Application\Port\RouteCarriers;
use Isa\Routing\Application\Port\RouteDepot;
use Isa\Routing\Application\Port\RouteOrders;
use Isa\Routing\Domain\DeliveryRoute;
use Isa\Routing\Domain\Exception\CarrierNotAvailable;
use Isa\Routing\Domain\Exception\OrderAlreadyInRoute;
use Isa\Routing\Domain\Exception\OrderNotRoutable;
use Isa\Routing\Domain\Exception\RouteOrderNotFound;
use Isa\Routing\Domain\Exception\StopWithoutLocation;
use Isa\Routing\Domain\LoadLimit;
use Isa\Routing\Domain\RouteId;
use Isa\Routing\Domain\RouteOptimizer;
use Isa\Routing\Domain\RouteRepository;
use Isa\Routing\Domain\RouteStop;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Application\TransactionManager;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\GeoPoint;

/**
 * Programa una ruta: valida mensajero activo, pedidos con coordenadas y que
 * no estén en otra ruta, revisa la capacidad del vehículo, ordena las paradas
 * y asigna cada pedido al mensajero.
 */
final class PlanRouteHandler
{
    /** Estados de pedido que se pueden programar. */
    private const ROUTABLE = ['created', 'prepared', 'assigned', 'incident'];

    public function __construct(
        private readonly RouteRepository $routes,
        private readonly RouteOrders $orders,
        private readonly RouteCarriers $carriers,
        private readonly RouteDepot $depot,
        private readonly RouteOptimizer $optimizer,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
    ) {}

    public function __invoke(PlanRouteCommand $command): DeliveryRoute
    {
        $companyId = $this->tenant->companyId();
        $carrier = $this->carriers->find($companyId, $command->carrierId);
        if ($carrier === null || ! $carrier->active) {
            throw new CarrierNotAvailable;
        }

        $found = $this->orders->find($companyId, $command->orderIds);
        $stops = [];
        foreach ($command->orderIds as $orderId) {
            $order = $found[$orderId] ?? throw new RouteOrderNotFound($orderId);
            if (! in_array($order->status, self::ROUTABLE, true)) {
                throw new OrderNotRoutable($order->trackingCode, $order->status);
            }
            if ($order->latitude === null || $order->longitude === null) {
                throw new StopWithoutLocation($order->trackingCode);
            }
            $stops[] = new RouteStop($order->id, new GeoPoint($order->latitude, $order->longitude), $order->weightKg, $order->packages);
        }

        $busy = $this->routes->ordersInOpenRoutes($companyId, $command->orderIds);
        if ($busy !== []) {
            throw new OrderAlreadyInRoute($found[$busy[0]]->trackingCode);
        }

        $start = $command->startLatitude !== null && $command->startLongitude !== null
            ? new GeoPoint($command->startLatitude, $command->startLongitude)
            : $this->depot->of($companyId);

        $route = DeliveryRoute::plan(
            RouteId::generate(),
            $companyId,
            CarrierId::fromString($carrier->id),
            $command->date,
            $command->name ?? sprintf('Ruta %s · %s', $carrier->name, $command->date),
            $start,
            $stops,
            new LoadLimit($carrier->capacityKg, $carrier->capacityPackages),
            $this->clock->now(),
            $command->optimize ? $this->optimizer : null,
        );

        $this->transactions->run(function () use ($route, $carrier, $command): void {
            $this->routes->save($route);
            foreach ($route->orderIds() as $orderId) {
                $this->orders->assign($orderId, $carrier->id, $command->plannedBy);
            }
        });

        return $route;
    }
}
