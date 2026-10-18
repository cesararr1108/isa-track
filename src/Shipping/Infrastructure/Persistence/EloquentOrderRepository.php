<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shared\Domain\ValueObject\UserId;
use Isa\Shipping\Domain\Address;
use Isa\Shipping\Domain\IncidentReason;
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Domain\OrderId;
use Isa\Shipping\Domain\OrderRepository;
use Isa\Shipping\Domain\OrderStatus;
use Isa\Shipping\Domain\Package;
use Isa\Shipping\Domain\PackageId;
use Isa\Shipping\Domain\Recipient;
use Isa\Shipping\Domain\StatusChange;
use Isa\Shipping\Domain\TrackingCode;

/**
 * Adaptador de persistencia: traduce el agregado Order <-> tablas
 * orders, order_packages y order_status_history.
 */
final class EloquentOrderRepository implements OrderRepository
{
    public function save(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $address = $order->address();

            OrderModel::query()->updateOrCreate(['id' => $order->id()->value()], [
                'company_id' => $order->companyId()->value(),
                'tracking_code' => $order->trackingCode()->value(),
                'external_reference' => $order->externalReference(),
                'status' => $order->status()->value,
                'carrier_id' => $order->carrierId()?->value(),
                'recipient_name' => $order->recipient()->name,
                'recipient_phone' => $order->recipient()->phone,
                'recipient_email' => $order->recipient()->email,
                'address_line' => $address->line,
                'city' => $address->city,
                'neighborhood' => $address->neighborhood,
                'address_reference' => $address->reference,
                'latitude' => $address->location?->latitude,
                'longitude' => $address->location?->longitude,
                'promised_at' => $order->promisedAt(),
                'created_at' => $order->createdAt(),
            ]);

            foreach ($order->packages() as $package) {
                PackageModel::query()->updateOrCreate(['id' => $package->id->value()], [
                    'order_id' => $order->id()->value(),
                    'code' => $package->code,
                    'description' => $package->description,
                    'weight_kg' => $package->weightKg,
                ]);
            }

            // El historial es de solo inserción: se guardan las entradas nuevas.
            $existing = StatusChangeModel::query()->where('order_id', $order->id()->value())->pluck('id')->all();
            foreach ($order->history() as $change) {
                if (in_array($change->id, $existing, true)) {
                    continue;
                }
                StatusChangeModel::query()->create([
                    'id' => $change->id,
                    'order_id' => $order->id()->value(),
                    'status' => $change->status->value,
                    'occurred_at' => $change->occurredAt,
                    'changed_by' => $change->changedBy?->value(),
                    'latitude' => $change->location?->latitude,
                    'longitude' => $change->location?->longitude,
                    'note' => $change->note,
                    'incident_reason' => $change->incidentReason?->value,
                ]);
            }
        });
    }

    public function find(CompanyId $companyId, OrderId $id): ?Order
    {
        $row = OrderModel::query()
            ->with(['packages', 'history'])
            ->where('company_id', $companyId->value())
            ->find($id->value());

        return $row === null ? null : $this->toDomain($row);
    }

    public function findByTrackingCode(TrackingCode $code): ?Order
    {
        $row = OrderModel::query()
            ->withoutGlobalScope('company')
            ->with(['packages', 'history'])
            ->where('tracking_code', $code->value())
            ->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function trackingCodeExists(TrackingCode $code): bool
    {
        return OrderModel::query()->withoutGlobalScope('company')->where('tracking_code', $code->value())->exists();
    }

    private function toDomain(OrderModel $row): Order
    {
        return Order::restore(
            OrderId::fromString($row->id),
            CompanyId::fromString($row->company_id),
            new TrackingCode($row->tracking_code),
            $row->external_reference,
            new Recipient($row->recipient_name, $row->recipient_phone, $row->recipient_email),
            new Address(
                $row->address_line,
                $row->city,
                $row->neighborhood,
                $row->address_reference,
                GeoPoint::fromNullable($row->latitude, $row->longitude),
            ),
            $row->packages->map(fn (PackageModel $p) => new Package(
                PackageId::fromString($p->id),
                $p->code,
                $p->description,
                $p->weight_kg,
            ))->all(),
            OrderStatus::from($row->status),
            $row->carrier_id !== null ? CarrierId::fromString($row->carrier_id) : null,
            $row->promised_at,
            $row->created_at,
            $row->history->map(fn (StatusChangeModel $h) => new StatusChange(
                $h->id,
                OrderStatus::from($h->status),
                $h->occurred_at,
                $h->changed_by !== null ? UserId::fromString($h->changed_by) : null,
                GeoPoint::fromNullable($h->latitude, $h->longitude),
                $h->note,
                $h->incident_reason !== null ? IncidentReason::from($h->incident_reason) : null,
            ))->all(),
        );
    }
}
