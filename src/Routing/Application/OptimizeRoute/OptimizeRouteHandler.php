<?php

declare(strict_types=1);

namespace Isa\Routing\Application\OptimizeRoute;

use Isa\Routing\Application\GetRoute\GetRouteHandler;
use Isa\Routing\Domain\DeliveryRoute;
use Isa\Routing\Domain\RouteOptimizer;
use Isa\Routing\Domain\RouteRepository;

final class OptimizeRouteHandler
{
    public function __construct(
        private readonly RouteRepository $routes,
        private readonly GetRouteHandler $getRoute,
        private readonly RouteOptimizer $optimizer,
    ) {}

    public function __invoke(string $routeId): DeliveryRoute
    {
        $route = $this->getRoute->load($routeId);
        $route->optimize($this->optimizer);
        $this->routes->save($route);

        return $route;
    }
}
