<?php

declare(strict_types=1);

namespace Isa\Routing\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Isa\Routing\Application\Port\RouteCarriers;
use Isa\Routing\Application\Port\RouteDepot;
use Isa\Routing\Application\Port\RouteOrders;
use Isa\Routing\Domain\RouteRepository;
use Isa\Routing\Infrastructure\Adapter\ConfigRouteDepot;
use Isa\Routing\Infrastructure\Adapter\FleetRouteCarriers;
use Isa\Routing\Infrastructure\Adapter\ShippingRouteOrders;
use Isa\Routing\Infrastructure\Persistence\EloquentRouteRepository;

final class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RouteRepository::class, EloquentRouteRepository::class);
        $this->app->bind(RouteOrders::class, ShippingRouteOrders::class);
        $this->app->bind(RouteCarriers::class, FleetRouteCarriers::class);
        $this->app->bind(RouteDepot::class, fn () => new ConfigRouteDepot(
            (float) config('isa.routing.depot.latitude'),
            (float) config('isa.routing.depot.longitude'),
        ));
    }
}
