<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\TrackOrder;

use InvalidArgumentException;
use Isa\Shipping\Application\Port\CarrierDirectory;
use Isa\Shipping\Application\Port\CompanyBrandingProvider;
use Isa\Shipping\Domain\Exception\OrderNotFound;
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Domain\OrderRepository;
use Isa\Shipping\Domain\OrderStatus;
use Isa\Shipping\Domain\StatusChange;
use Isa\Shipping\Domain\TrackingCode;

/**
 * Rastreo público (sin login). Solo expone lo necesario: nada de teléfonos,
 * direcciones exactas ni usuarios internos (Ley 1581 de 2012).
 */
final class TrackOrderHandler
{
    /** Decimales de la ubicación del conductor: 3 ≈ 110 m, ubicación "aproximada". */
    private const LOCATION_PRECISION = 3;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly CompanyBrandingProvider $branding,
        private readonly CarrierDirectory $carriers,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(string $trackingCode): array
    {
        $order = $this->find($trackingCode);

        $inRoute = $order->status() === OrderStatus::InRoute;
        $location = $inRoute ? $order->lastKnownLocation() : null;
        $carrier = $inRoute && $order->carrierId() !== null
            ? $this->carriers->publicProfile($order->companyId(), $order->carrierId())
            : null;
        $carrierName = $carrier['name'] ?? null;

        return [
            'tracking_code' => $order->trackingCode()->value(),
            'status' => $order->status()->value,
            'company' => $this->branding->forCompany($order->companyId()),
            'recipient_first_name' => $order->recipient()->firstName(),
            'destination' => array_filter([$order->address()->neighborhood, $order->address()->city]),
            'packages_count' => count($order->packages()),
            'promised_at' => $order->promisedAt()?->format(DATE_ATOM),
            'carrier_name' => $carrierName !== null ? explode(' ', $carrierName)[0] : null,
            // Mientras va en camino el cliente ve quién le lleva el pedido (para reconocerlo en la puerta).
            'carrier' => $carrier === null ? null : [
                'first_name' => explode(' ', trim($carrier['name']))[0],
                'vehicle_type' => $carrier['vehicle_type'],
                'vehicle_plate' => $carrier['vehicle_plate'],
                'has_photo' => $carrier['photo_path'] !== null,
            ],
            'delivery_fee' => $order->deliveryCharge()?->fee->amount,
            'zone_name' => $order->deliveryCharge()?->zoneName,
            'destination_point' => $order->address()->location === null ? null : [
                // Punto del barrio, no de la casa: ~1 km de precisión.
                'latitude' => round($order->address()->location->latitude, 2),
                'longitude' => round($order->address()->location->longitude, 2),
            ],
            'received_by' => $order->delivery()?->proof->receiverName !== null
                ? explode(' ', trim($order->delivery()->proof->receiverName))[0]
                : null,
            'approximate_location' => $location === null ? null : [
                'latitude' => round($location->latitude, self::LOCATION_PRECISION),
                'longitude' => round($location->longitude, self::LOCATION_PRECISION),
            ],
            'timeline' => array_map(fn (StatusChange $c) => [
                'status' => $c->status->value,
                'occurred_at' => $c->occurredAt->format(DATE_ATOM),
                'incident_reason' => $c->incidentReason?->value,
            ], $order->history()),
        ];
    }

    /** Ruta interna de la foto del mensajero, solo mientras el pedido va en camino. */
    public function carrierPhotoPath(string $trackingCode): ?string
    {
        $order = $this->find($trackingCode);
        if ($order->status() !== OrderStatus::InRoute || $order->carrierId() === null) {
            return null;
        }

        return $this->carriers->publicProfile($order->companyId(), $order->carrierId())['photo_path'] ?? null;
    }

    private function find(string $trackingCode): Order
    {
        try {
            $code = new TrackingCode($trackingCode);
        } catch (InvalidArgumentException) {
            throw new OrderNotFound($trackingCode);
        }

        return $this->orders->findByTrackingCode($code) ?? throw new OrderNotFound($trackingCode);
    }
}
