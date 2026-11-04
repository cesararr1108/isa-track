<?php

declare(strict_types=1);

namespace Isa\Routing\Infrastructure\Adapter;

use Isa\Routing\Application\Port\RouteOrder;
use Isa\Routing\Application\Port\RouteOrders;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shipping\Application\AssignCarrier\AssignCarrierCommand;
use Isa\Shipping\Application\AssignCarrier\AssignCarrierHandler;
use Isa\Shipping\Infrastructure\Persistence\OrderModel;
use Isa\Shipping\Infrastructure\Persistence\PackageModel;

/** Adaptador hacia Pedidos: lee con una consulta liviana y asigna con el caso de uso de Pedidos. */
final class ShippingRouteOrders implements RouteOrders
{
    public function __construct(private readonly AssignCarrierHandler $assignCarrier) {}

    public function find(CompanyId $companyId, array $orderIds): array
    {
        $valid = array_values(array_filter($orderIds, fn ($id) => is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id)));
        if ($valid === []) {
            return [];
        }

        $result = [];
        $rows = OrderModel::query()
            ->with('packages')
            ->where('company_id', $companyId->value())
            ->whereIn('id', $valid)
            ->get();

        foreach ($rows as $o) {
            $result[$o->id] = new RouteOrder(
                id: $o->id,
                trackingCode: $o->tracking_code,
                status: $o->status,
                recipientName: $o->recipient_name,
                addressLine: $o->address_line,
                neighborhood: $o->neighborhood,
                latitude: $o->latitude,
                longitude: $o->longitude,
                weightKg: (float) $o->packages->sum(fn (PackageModel $p) => $p->weight_kg ?? 0),
                packages: $o->packages->count(),
                cashOnDelivery: $o->cash_on_delivery,
                carrierId: $o->carrier_id,
            );
        }

        return $result;
    }

    public function routable(CompanyId $companyId, int $limit): array
    {
        $ids = OrderModel::query()
            ->where('company_id', $companyId->value())
            ->whereIn('status', ['created', 'prepared', 'assigned', 'incident'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id')
            ->all();

        return array_values($this->find($companyId, $ids));
    }

    public function assign(string $orderId, string $carrierId, ?string $byUserId): void
    {
        $current = OrderModel::query()->whereKey($orderId)->first(['carrier_id', 'status']);
        if ($current !== null && $current->carrier_id === $carrierId && $current->status === 'assigned') {
            return;
        }

        ($this->assignCarrier)(new AssignCarrierCommand($orderId, $carrierId, $byUserId));
    }
}
