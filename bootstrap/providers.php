<?php

use App\Providers\AppServiceProvider;
use Isa\Companies\Infrastructure\CompaniesServiceProvider;
use Isa\Fleet\Infrastructure\FleetServiceProvider;
use Isa\Shared\Infrastructure\SharedServiceProvider;
use Isa\Shipping\Infrastructure\ShippingServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    CompaniesServiceProvider::class,
    FleetServiceProvider::class,
    ShippingServiceProvider::class,
];
