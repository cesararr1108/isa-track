<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Infrastructure\Auth\DriverScope;
use Isa\Shipping\Application\ListOrders\ListOrdersHandler;
use Isa\Zones\Application\ListZones\ListZonesHandler;

/**
 * Mapa en vivo: pedidos del día por estado, última ubicación de cada mensajero
 * (reportada desde su app en las últimas 3 horas) y las zonas de fondo.
 * La página se actualiza sola cada 15 segundos (solo pide pedidos y mensajeros).
 */
final class LiveMapController
{
    private const LOCATION_MAX_AGE_MINUTES = 180;

    public function __invoke(Request $request, ListOrdersHandler $orders, ListCarriersHandler $carriers, ListZonesHandler $zones, Clock $clock): Response
    {
        $now = $clock->now();
        $driver = DriverScope::carrierId($request);

        return Inertia::render('live/pages/LiveMap', [
            'orders' => fn () => array_values(array_filter(
                $orders(['board_since' => $now->format('Y-m-d'), 'carrier_id' => $driver], 1, 100)['data'],
                fn (array $o) => $o['latitude'] !== null,
            )),
            'carriers' => fn () => array_values(array_map(fn (array $c) => [
                'id' => $c['id'], 'name' => $c['name'], 'vehicle_plate' => $c['vehicle_plate'], 'vehicle_type' => $c['vehicle_type'],
                'location' => $c['last_location'],
                'minutes_ago' => (int) round(($now->getTimestamp() - (new DateTimeImmutable($c['last_location']['at']))->getTimestamp()) / 60),
            ], array_filter(
                $carriers(onlyActive: true),
                fn (array $c) => $c['last_location'] !== null && ($driver === null || $c['id'] === $driver)
                    && (new DateTimeImmutable($c['last_location']['at'])) > $now->modify('-'.self::LOCATION_MAX_AGE_MINUTES.' minutes'),
            ))),
            'zones' => $zones(onlyActive: true),
            'depot' => [(float) config('isa.routing.depot.latitude'), (float) config('isa.routing.depot.longitude')],
            'statuses' => config('isa.statuses'),
            'updatedAt' => $now->format(DATE_ATOM),
        ]);
    }
}
