<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Fleet\Infrastructure\Persistence\EloquentCarrierRepository;

final class FleetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CarrierRepository::class, EloquentCarrierRepository::class);
    }
}
