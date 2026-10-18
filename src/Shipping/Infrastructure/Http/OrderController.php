<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Infrastructure\Auth\Role;
use Isa\Shipping\Application\AssignCarrier\AssignCarrierCommand;
use Isa\Shipping\Application\AssignCarrier\AssignCarrierHandler;
use Isa\Shipping\Application\ChangeOrderStatus\ChangeOrderStatusCommand;
use Isa\Shipping\Application\ChangeOrderStatus\ChangeOrderStatusHandler;
use Isa\Shipping\Application\CreateOrder\CreateOrderHandler;
use Isa\Shipping\Application\GetOrder\GetOrderHandler;
use Isa\Shipping\Application\ListOrders\ListOrdersHandler;
use Isa\Shipping\Application\OrderPresenter;
use Isa\Shipping\Domain\Exception\OrderNotFound;
use Isa\Shipping\Domain\IncidentReason;
use Isa\Shipping\Domain\OrderStatus;

/**
 * Controlador delgado: valida la entrada, arma el comando, llama al caso
 * de uso y devuelve JSON. Laravel inyecta los handlers en cada método.
 */
final class OrderController
{
    public function index(Request $request, ListOrdersHandler $listOrders): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'carrier_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        // El conductor solo ve sus propios pedidos.
        if ($this->driverCarrierId($request) !== null) {
            $filters['carrier_id'] = $this->driverCarrierId($request);
        }

        return new JsonResponse($listOrders(
            $filters,
            (int) ($filters['page'] ?? 1),
            (int) ($filters['per_page'] ?? 20),
        ));
    }

    public function store(CreateOrderRequest $request, CreateOrderHandler $createOrder): JsonResponse
    {
        $order = $createOrder($request->toCommand(config('isa.shipping.default_city')));

        return new JsonResponse(['data' => OrderPresenter::toArray($order)], 201);
    }

    public function show(Request $request, string $order, GetOrderHandler $getOrder): JsonResponse
    {
        $found = $getOrder($order);

        $driverCarrier = $this->driverCarrierId($request);
        if ($driverCarrier !== null && ! $found->isAssignedTo(CarrierId::fromString($driverCarrier))) {
            throw new OrderNotFound($order);
        }

        return new JsonResponse(['data' => OrderPresenter::toArray($found)]);
    }

    public function assign(Request $request, string $order, AssignCarrierHandler $assignCarrier): JsonResponse
    {
        $data = $request->validate(['carrier_id' => ['required', 'uuid']]);

        $updated = $assignCarrier(new AssignCarrierCommand(
            $order,
            $data['carrier_id'],
            (string) $request->user()->getAuthIdentifier(),
        ));

        return new JsonResponse(['data' => OrderPresenter::toArray($updated)]);
    }

    public function changeStatus(Request $request, string $order, ChangeOrderStatusHandler $changeStatus): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'note' => ['nullable', 'string', 'max:500'],
            'incident_reason' => ['nullable', Rule::enum(IncidentReason::class)],
        ]);

        $updated = $changeStatus(new ChangeOrderStatusCommand(
            orderId: $order,
            status: $data['status'],
            changedBy: (string) $request->user()->getAuthIdentifier(),
            actorCarrierId: $this->driverCarrierId($request),
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            note: $data['note'] ?? null,
            incidentReason: $data['incident_reason'] ?? null,
        ));

        return new JsonResponse(['data' => OrderPresenter::toArray($updated)]);
    }

    private function driverCarrierId(Request $request): ?string
    {
        $user = $request->user();

        // Un conductor sin transportador vinculado no puede ver ni mover ningún pedido.
        return $user->role === Role::Driver ? ($user->carrier_id ?? '00000000-0000-0000-0000-000000000000') : null;
    }
}
