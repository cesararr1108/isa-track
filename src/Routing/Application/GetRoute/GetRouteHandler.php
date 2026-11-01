<?php

declare(strict_types=1);

namespace Isa\Routing\Application\GetRoute;

use Isa\Routing\Application\Port\RouteCarriers;
use Isa\Routing\Application\Port\RouteOrders;
use Isa\Routing\Application\RoutePresenter;
use Isa\Routing\Domain\DeliveryRoute;
use Isa\Routing\Domain\Exception\RouteNotFound;
use Isa\Routing\Domain\RouteId;
use Isa\Routing\Domain\RouteRepository;
use Isa\Shared\Application\TenantContext;

final class GetRouteHandler
{
    public function __construct(
        private readonly RouteRepository $routes,
        private readonly RouteOrders $orders,
        private readonly RouteCarriers $carriers,
        private readonly TenantContext $tenant,
    ) {}

    public function load(string $routeId): DeliveryRoute
    {
        return $this->routes->find($this->tenant->companyId(), RouteId::fromString($routeId))
            ?? throw new RouteNotFound($routeId);
    }

    /** @return array<string, mixed> */
    public function present(DeliveryRoute $route): array
    {
        return RoutePresenter::toArray(
            $route,
            $this->carriers->find($route->companyId(), $route->carrierId()->value()),
            $this->orders->find($route->companyId(), $route->orderIds()),
        );
    }

    /** @return array<string, mixed> */
    public function geoJson(string $routeId): array
    {
        $route = $this->load($routeId);

        return RoutePresenter::geoJson($route, $this->orders->find($route->companyId(), $route->orderIds()));
    }

    /** @return array<string, mixed> */
    public function __invoke(string $routeId): array
    {
        return $this->present($this->load($routeId));
    }
}
