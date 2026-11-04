<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Http\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Infrastructure\Auth\DriverScope;
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
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Infrastructure\Http\CreateOrderRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pantalla de pedidos del panel (despacho). Usa los mismos casos de uso que la API. */
final class OrderPageController
{
    public function __construct(private readonly Clock $clock) {}

    public function index(Request $request, ListOrdersHandler $list, GetOrderHandler $getOrder, ListCarriersHandler $carriers): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(config('isa.statuses')))],
            'search' => ['nullable', 'string', 'max:60'],
            'order' => ['nullable', 'uuid'],
        ]);
        $driver = DriverScope::carrierId($request);

        $orders = $list([
            'status' => $filters['status'] ?? null,
            'search' => $filters['search'] ?? null,
            'carrier_id' => $driver,
            'board_since' => $this->clock->now()->format('Y-m-d'),
        ], 1, 100);

        $selected = null;
        if (isset($filters['order'])) {
            try {
                $found = $getOrder($filters['order']);
                if ($driver === null || $found->isAssignedTo(CarrierId::fromString($driver))) {
                    $selected = $this->present($found);
                }
            } catch (OrderNotFound) {
                $selected = null;
            }
        }

        return Inertia::render('orders/pages/OrdersIndex', [
            'orders' => $orders['data'],
            'total' => $orders['meta']['total'],
            'filters' => ['status' => $filters['status'] ?? null, 'search' => $filters['search'] ?? null],
            'selected' => $selected,
            'carriers' => array_values(array_map(
                fn (array $c) => ['id' => $c['id'], 'name' => $c['name'], 'vehicle_plate' => $c['vehicle_plate']],
                $carriers(onlyActive: true),
            )),
            'statuses' => config('isa.statuses'),
            'transitions' => config('isa.shipping.transitions'),
            'incidentReasons' => config('isa.incident_reasons'),
            'paymentMethods' => config('isa.payment_methods'),
        ]);
    }

    public function store(CreateOrderRequest $request, CreateOrderHandler $create): RedirectResponse
    {
        $order = $create($request->toCommand(config('isa.shipping.default_city')));

        return redirect()->route('panel.orders.index', ['order' => $order->id()->value()])
            ->with('success', "Pedido {$order->trackingCode()->value()} creado. Se avisó al cliente.");
    }

    public function assign(Request $request, string $order, AssignCarrierHandler $assign): RedirectResponse
    {
        $data = $request->validate(['carrier_id' => ['required', 'uuid']]);
        $assign(new AssignCarrierCommand($order, $data['carrier_id'], (string) $request->user()->getAuthIdentifier()));

        return back()->with('success', 'Pedido asignado.');
    }

    public function changeStatus(Request $request, string $order, ChangeOrderStatusHandler $change): RedirectResponse
    {
        $data = $request->validate([
            // "Entregado" solo se registra con prueba de entrega desde la app del mensajero.
            'status' => ['required', Rule::in(['prepared', 'assigned', 'in_route', 'incident', 'returned'])],
            'incident_reason' => ['nullable', 'required_if:status,incident', Rule::enum(IncidentReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $change(new ChangeOrderStatusCommand(
            orderId: $order,
            status: $data['status'],
            changedBy: (string) $request->user()->getAuthIdentifier(),
            actorCarrierId: DriverScope::carrierId($request),
            note: $data['note'] ?? null,
            incidentReason: $data['incident_reason'] ?? null,
        ));

        return back()->with('success', 'Estado actualizado.');
    }

    /** Foto o firma de la entrega (solo con acceso al pedido). */
    public function evidence(Request $request, string $order, string $kind, GetOrderHandler $getOrder): StreamedResponse
    {
        $found = $getOrder($order);
        $driver = DriverScope::carrierId($request);
        if ($driver !== null && ! $found->isAssignedTo(CarrierId::fromString($driver))) {
            throw new OrderNotFound($order);
        }
        $proof = $found->delivery()?->proof;
        $path = $kind === 'photo' ? $proof?->photoPath : $proof?->signaturePath;
        abort_if($path === null, 404);

        return Storage::disk(config('isa.delivery_proof.disk'))->response($path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    /** @return array<string, mixed> */
    private function present(Order $order): array
    {
        $data = OrderPresenter::toArray($order);
        if ($data['delivery'] !== null) {
            $id = $order->id()->value();
            $data['delivery']['photo_url'] = $data['delivery']['has_photo'] ? route('panel.orders.evidence', [$id, 'photo']) : null;
            $data['delivery']['signature_url'] = $data['delivery']['has_signature'] ? route('panel.orders.evidence', [$id, 'signature']) : null;
        }

        return $data;
    }
}
