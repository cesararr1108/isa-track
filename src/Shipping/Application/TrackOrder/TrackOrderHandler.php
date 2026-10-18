<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\TrackOrder;

use InvalidArgumentException;
use Isa\Shipping\Application\Port\CarrierDirectory;
use Isa\Shipping\Application\Port\CompanyBrandingProvider;
use Isa\Shipping\Domain\Exception\OrderNotFound;
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
        try {
            $code = new TrackingCode($trackingCode);
        } catch (InvalidArgumentException) {
            throw new OrderNotFound($trackingCode);
        }

        $order = $this->orders->findByTrackingCode($code) ?? throw new OrderNotFound($trackingCode);

        $inRoute = $order->status() === OrderStatus::InRoute;
        $location = $inRoute ? $order->lastKnownLocation() : null;
        $carrierName = $inRoute && $order->carrierId() !== null
            ? $this->carriers->displayName($order->companyId(), $order->carrierId())
            : null;

        return [
            'tracking_code' => $order->trackingCode()->value(),
            'status' => $order->status()->value,
            'company' => $this->branding->forCompany($order->companyId()),
            'recipient_first_name' => $order->recipient()->firstName(),
            'destination' => array_filter([$order->address()->neighborhood, $order->address()->city]),
            'packages_count' => count($order->packages()),
            'promised_at' => $order->promisedAt()?->format(DATE_ATOM),
            'carrier_name' => $carrierName !== null ? explode(' ', $carrierName)[0] : null,
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
}
