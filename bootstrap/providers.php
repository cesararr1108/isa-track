<?php

use App\Providers\AppServiceProvider;
use Isa\Cash\Infrastructure\CashServiceProvider;
use Isa\Companies\Infrastructure\CompaniesServiceProvider;
use Isa\Fleet\Infrastructure\FleetServiceProvider;
use Isa\Reports\Infrastructure\ReportsServiceProvider;
use Isa\Routing\Infrastructure\RoutingServiceProvider;
use Isa\Shared\Infrastructure\SharedServiceProvider;
use Isa\Shipping\Infrastructure\ShippingServiceProvider;
use Isa\Zones\Infrastructure\ZonesServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    CompaniesServiceProvider::class,
    FleetServiceProvider::class,
    ZonesServiceProvider::class,
    ShippingServiceProvider::class,
    CashServiceProvider::class,
    RoutingServiceProvider::class,
    ReportsServiceProvider::class,
];
