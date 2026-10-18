<?php

declare(strict_types=1);

namespace Isa\Cash\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Isa\Cash\Application\CloseCashRegister\CloseCashRegisterHandler;
use Isa\Cash\Application\Port\CarrierLookup;
use Isa\Cash\Application\Port\CollectedPayments;
use Isa\Cash\Domain\CashClosingRepository;
use Isa\Cash\Infrastructure\Adapter\FleetCarrierLookup;
use Isa\Cash\Infrastructure\Adapter\ShippingCollectedPayments;
use Isa\Cash\Infrastructure\Persistence\EloquentCashClosingRepository;

final class CashServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CashClosingRepository::class, EloquentCashClosingRepository::class);
        $this->app->bind(CarrierLookup::class, FleetCarrierLookup::class);
        $this->app->bind(CollectedPayments::class, fn () => new ShippingCollectedPayments(config('app.timezone')));

        $this->app->when(CloseCashRegisterHandler::class)
            ->needs('$requireNoteOnDifference')
            ->giveConfig('isa.cash.require_note_on_difference');
    }
}
