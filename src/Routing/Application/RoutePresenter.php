<?php

declare(strict_types=1);

namespace Isa\Routing\Application;

use Isa\Routing\Application\Port\RouteCarrier;
use Isa\Routing\Application\Port\RouteOrder;
use Isa\Routing\Domain\DeliveryRoute;
use Isa\Routing\Domain\Distance;
use Isa\Routing\Domain\RouteStop;

final class RoutePresenter
{
    /**
     * @param  array<string, RouteOrder>  $orders
     * @return array<string, mixed>
     */
    public static function toArray(DeliveryRoute $route, ?RouteCarrier $carrier = null, array $orders = []): array
    {
        $prev = $route->start();
        $stops = [];
        foreach ($route->stops() as $i => $stop) {
            $order = $orders[$stop->orderId] ?? null;
            $stops[] = [
                'sequence' => $i + 1,
                'order_id' => $stop->orderId,
                'tracking_code' => $order?->trackingCode,
                'status' => $order?->status,
                'recipient_name' => $order?->recipientName,
                'address' => $order?->addressLine,
                'neighborhood' => $order?->neighborhood,
                'cash_on_delivery' => $order?->cashOnDelivery,
                'latitude' => $stop->location->latitude,
                'longitude' => $stop->location->longitude,
                'weight_kg' => $stop->weightKg,
                'packages' => $stop->packages,
                'leg_km' => round(Distance::km($prev, $stop->location), 2),
            ];
            $prev = $stop->location;
        }

        $delivered = count(array_filter($stops, fn (array $s) => $s['status'] === 'delivered'));

        return [
            'id' => $route->id()->value(),
            'name' => $route->name(),
            'date' => $route->date(),
            'status' => $route->status()->value,
            'carrier' => $carrier === null ? ['id' => $route->carrierId()->value()] : [
                'id' => $carrier->id,
                'name' => $carrier->name,
                'vehicle_plate' => $carrier->vehiclePlate,
                'vehicle_type' => $carrier->vehicleType,
                'capacity_kg' => $carrier->capacityKg,
                'capacity_packages' => $carrier->capacityPackages,
            ],
            'start' => ['latitude' => $route->start()->latitude, 'longitude' => $route->start()->longitude],
            'stops_count' => count($stops),
            'delivered_count' => $delivered,
            'total_weight_kg' => round($route->totalWeightKg(), 2),
            'total_packages' => $route->totalPackages(),
            'distance_km' => round($route->distanceKm(), 2),
            'created_at' => $route->createdAt()->format(DATE_ATOM),
            'started_at' => $route->startedAt()?->format(DATE_ATOM),
            'finished_at' => $route->finishedAt()?->format(DATE_ATOM),
            'stops' => $stops,
        ];
    }

    /**
     * La ruta como GeoJSON (para pintarla en Leaflet, Mapbox o Google Maps).
     *
     * @return array<string, mixed>
     */
    public static function geoJson(DeliveryRoute $route, array $orders = []): array
    {
        $line = [[$route->start()->longitude, $route->start()->latitude]];
        $features = [[
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => $line[0]],
            'properties' => ['kind' => 'start', 'name' => 'Punto de partida'],
        ]];
        foreach ($route->stops() as $i => $stop) {
            /** @var RouteStop $stop */
            $coord = [$stop->location->longitude, $stop->location->latitude];
            $line[] = $coord;
            $order = $orders[$stop->orderId] ?? null;
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => $coord],
                'properties' => [
                    'kind' => 'stop',
                    'sequence' => $i + 1,
                    'order_id' => $stop->orderId,
                    'tracking_code' => $order?->trackingCode,
                    'status' => $order?->status,
                    'recipient_name' => $order?->recipientName,
                    'address' => $order?->addressLine,
                ],
            ];
        }
        array_unshift($features, [
            'type' => 'Feature',
            'geometry' => ['type' => 'LineString', 'coordinates' => $line],
            'properties' => ['kind' => 'path', 'distance_km' => round($route->distanceKm(), 2)],
        ]);

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
