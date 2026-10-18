<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Isa\Shipping\Application\NotifyCustomer\NotifyCustomerOnStatusChange;
use Isa\Shipping\Application\Port\CarrierDirectory;
use Isa\Shipping\Application\Port\CompanyBrandingProvider;
use Isa\Shipping\Application\Port\CustomerNotifier;
use Isa\Shipping\Application\Port\OrderQueries;
use Isa\Shipping\Domain\Event\OrderStatusChanged;
use Isa\Shipping\Domain\OrderRepository;
use Isa\Shipping\Domain\StatusTransitionPolicy;
use Isa\Shipping\Domain\TrackingCodeGenerator;
use Isa\Shipping\Domain\TransitionMap;
use Isa\Shipping\Infrastructure\Adapter\CompaniesBrandingProvider;
use Isa\Shipping\Infrastructure\Adapter\FleetCarrierDirectory;
use Isa\Shipping\Infrastructure\Adapter\RandomTrackingCodeGenerator;
use Isa\Shipping\Infrastructure\Notification\LogCustomerNotifier;
use Isa\Shipping\Infrastructure\Persistence\EloquentOrderQueries;
use Isa\Shipping\Infrastructure\Persistence\EloquentOrderRepository;

/**
 * Cableado del módulo Pedidos: puerto (interfaz) => adaptador (implementación).
 * Para cambiar una pieza (otra BD, WhatsApp real, otro generador de códigos)
 * se cambia la línea correspondiente aquí, sin tocar dominio ni casos de uso.
 */
final class ShippingServiceProvider extends ServiceProvider
{
    /** Notificadores disponibles; se elige con ISA_NOTIFIER en el .env. */
    private const NOTIFIERS = [
        'log' => LogCustomerNotifier::class,
        // 'whatsapp' => WhatsAppCustomerNotifier::class,
    ];

    public function register(): void
    {
        $this->app->bind(OrderRepository::class, EloquentOrderRepository::class);
        $this->app->bind(OrderQueries::class, EloquentOrderQueries::class);
        $this->app->bind(CarrierDirectory::class, FleetCarrierDirectory::class);
        $this->app->bind(CompanyBrandingProvider::class, CompaniesBrandingProvider::class);

        $this->app->singleton(StatusTransitionPolicy::class, fn () => new TransitionMap(config('isa.shipping.transitions')));

        $this->app->bind(TrackingCodeGenerator::class, fn () => new RandomTrackingCodeGenerator(
            (string) config('isa.shipping.tracking_code.prefix'),
            (int) config('isa.shipping.tracking_code.length'),
        ));

        $this->app->bind(CustomerNotifier::class, function ($app) {
            $driver = config('isa.notifications.driver');

            return $app->make(self::NOTIFIERS[$driver] ?? throw new InvalidArgumentException("Notificador <$driver> no soportado."));
        });

        $this->app->bind(NotifyCustomerOnStatusChange::class, fn ($app) => new NotifyCustomerOnStatusChange(
            $app->make(CustomerNotifier::class),
            config('isa.notifications.templates'),
            url('/rastreo'),
        ));
    }

    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, [NotifyCustomerOnStatusChange::class, 'handle']);
    }
}
