<?php

declare(strict_types=1);

namespace Isa\Zones\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Isa\Zones\Domain\ZoneRepository;
use Isa\Zones\Infrastructure\Persistence\EloquentZoneRepository;

final class ZonesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ZoneRepository::class, EloquentZoneRepository::class);
    }
}
