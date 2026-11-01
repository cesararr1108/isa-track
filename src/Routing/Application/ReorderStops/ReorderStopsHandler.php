<?php

declare(strict_types=1);

namespace Isa\Routing\Application\ReorderStops;

use Isa\Routing\Application\GetRoute\GetRouteHandler;
use Isa\Routing\Domain\DeliveryRoute;
use Isa\Routing\Domain\RouteRepository;

/** El despachador cambia a mano el orden de las paradas. */
final class ReorderStopsHandler
{
    public function __construct(
        private readonly RouteRepository $routes,
        private readonly GetRouteHandler $getRoute,
    ) {}

    /** @param list<string> $orderIds */
    public function __invoke(string $routeId, array $orderIds): DeliveryRoute
    {
        $route = $this->getRoute->load($routeId);
        $route->reorder($orderIds);
        $this->routes->save($route);

        return $route;
    }
}
