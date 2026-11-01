<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Isa\Fleet\Application\Port\CarrierPhotoStorage;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Fleet\Infrastructure\Adapter\LaravelCarrierPhotoStorage;
use Isa\Fleet\Infrastructure\Persistence\EloquentCarrierRepository;

final class FleetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CarrierRepository::class, EloquentCarrierRepository::class);
        $this->app->bind(CarrierPhotoStorage::class, fn () => new LaravelCarrierPhotoStorage(
            Storage::disk(config('isa.fleet.photo_disk')),
        ));
    }
}
