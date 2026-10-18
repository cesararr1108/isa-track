<?php

declare(strict_types=1);

namespace Isa\Shipping\Application;

use Isa\Shipping\Domain\Order;
use Isa\Shipping\Domain\Package;
use Isa\Shipping\Domain\StatusChange;

/** Convierte el agregado en un arreglo plano para responder (vista interna/privada). */
final class OrderPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(Order $order): array
    {
        $address = $order->address();

        return [
            'id' => $order->id()->value(),
            'tracking_code' => $order->trackingCode()->value(),
            'external_reference' => $order->externalReference(),
            'status' => $order->status()->value,
            'carrier_id' => $order->carrierId()?->value(),
            'promised_at' => $order->promisedAt()?->format(DATE_ATOM),
            'created_at' => $order->createdAt()->format(DATE_ATOM),
            'recipient' => [
                'name' => $order->recipient()->name,
                'phone' => $order->recipient()->phone,
                'email' => $order->recipient()->email,
            ],
            'address' => [
                'line' => $address->line,
                'city' => $address->city,
                'neighborhood' => $address->neighborhood,
                'reference' => $address->reference,
                'latitude' => $address->location?->latitude,
                'longitude' => $address->location?->longitude,
            ],
            'packages' => array_map(fn (Package $p) => [
                'id' => $p->id->value(),
                'code' => $p->code,
                'description' => $p->description,
                'weight_kg' => $p->weightKg,
            ], $order->packages()),
            'history' => array_map(fn (StatusChange $c) => [
                'status' => $c->status->value,
                'occurred_at' => $c->occurredAt->format(DATE_ATOM),
                'changed_by' => $c->changedBy?->value(),
                'latitude' => $c->location?->latitude,
                'longitude' => $c->location?->longitude,
                'note' => $c->note,
                'incident_reason' => $c->incidentReason?->value,
            ], $order->history()),
        ];
    }
}
