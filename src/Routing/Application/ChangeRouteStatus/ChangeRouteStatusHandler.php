<?php

declare(strict_types=1);

namespace Isa\Routing\Application\ChangeRouteStatus;

use Isa\Routing\Application\GetRoute\GetRouteHandler;
use Isa\Routing\Domain\DeliveryRoute;
use Isa\Routing\Domain\Exception\RouteNotFound;
use Isa\Routing\Domain\RouteRepository;
use Isa\Routing\Domain\RouteStatus;
use Isa\Shared\Domain\Clock;

/** Iniciar, completar o cancelar una ruta. */
final class ChangeRouteStatusHandler
{
    public function __construct(
        private readonly RouteRepository $routes,
        private readonly GetRouteHandler $getRoute,
        private readonly Clock $clock,
    ) {}

    public function __invoke(string $routeId, string $status, ?string $onlyCarrierId = null): DeliveryRoute
    {
        $route = $this->getRoute->load($routeId);
        if ($onlyCarrierId !== null && $route->carrierId()->value() !== $onlyCarrierId) {
            throw new RouteNotFound($routeId);
        }
        $route->changeStatus(RouteStatus::from($status), $this->clock->now());
        $this->routes->save($route);

        return $route;
    }
}
