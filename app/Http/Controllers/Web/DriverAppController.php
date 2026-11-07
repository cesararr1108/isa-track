<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Cash\Application\GetCashSummary\GetCashSummaryHandler;
use Isa\Fleet\Application\ReportLocation\ReportLocationHandler;
use Isa\Routing\Application\ListRoutes\ListRoutesHandler;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Infrastructure\Auth\DriverScope;
use Isa\Shipping\Application\ChangeOrderStatus\ChangeOrderStatusCommand;
use Isa\Shipping\Application\ChangeOrderStatus\ChangeOrderStatusHandler;
use Isa\Shipping\Application\DeliverOrder\DeliverOrderHandler;
use Isa\Shipping\Application\GetOrder\GetOrderHandler;
use Isa\Shipping\Application\ListOrders\ListOrdersHandler;
use Isa\Shipping\Application\OrderPresenter;
use Isa\Shipping\Domain\Exception\OrderNotFound;
use Isa\Shipping\Domain\IncidentReason;
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Infrastructure\Http\DeliverOrderRequest;

/**
 * App del mensajero (PWA en /mensajero). Misma sesión y CSRF que el panel y los
 * mismos casos de uso que la API: aquí solo se arma la vista móvil del día.
 * Siempre muestra lo del transportador vinculado al usuario; nunca el de otro.
 */
final class DriverAppController
{
    public function __construct(private readonly Clock $clock) {}

    public function home(Request $request, ListRoutesHandler $routes, ListOrdersHandler $orders, GetCashSummaryHandler $cash): Response
    {
        $carrier = $request->user()->carrier_id;
        $today = $this->clock->now()->format('Y-m-d');

        return Inertia::render('driver/pages/DriverHome', [
            'today' => $today,
            'linked' => $carrier !== null,
            'routes' => $carrier === null ? [] : array_values(array_filter(
                $routes(['date' => $today, 'carrier_id' => $carrier]),
                fn (array $r) => $r['status'] !== 'cancelled',
            )),
            'orders' => $carrier === null ? [] : $orders(['carrier_id' => $carrier, 'board_since' => $today], 1, 100)['data'],
            'cash' => $carrier === null ? null : $cash($carrier, $today),
            'statuses' => config('isa.statuses'),
            'paymentMethods' => config('isa.payment_methods'),
        ]);
    }

    public function order(Request $request, string $order, GetOrderHandler $getOrder): Response
    {
        $found = $this->ownOrder($request, $order, $getOrder);
        $data = OrderPresenter::toArray($found);
        if ($data['delivery'] !== null) {
            $data['delivery']['photo_url'] = $data['delivery']['has_photo'] ? route('panel.orders.evidence', [$order, 'photo']) : null;
            $data['delivery']['signature_url'] = $data['delivery']['has_signature'] ? route('panel.orders.evidence', [$order, 'signature']) : null;
        }

        return Inertia::render('driver/pages/DriverOrder', [
            'order' => $data,
            'statuses' => config('isa.statuses'),
            'incidentReasons' => config('isa.incident_reasons'),
            'paymentMethods' => config('isa.payment_methods'),
            'proofRequired' => config('isa.delivery_proof.require'),
        ]);
    }

    /** "Salir a entregar" (en camino) o "Reportar novedad", con la ubicación del celular si la hay. */
    public function changeStatus(Request $request, string $order, ChangeOrderStatusHandler $change): RedirectResponse
    {
        $d = $request->validate([
            'status' => ['required', Rule::in(['in_route', 'incident'])],
            'incident_reason' => ['nullable', 'required_if:status,incident', Rule::enum(IncidentReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ], [], ['incident_reason' => 'motivo']);

        $change(new ChangeOrderStatusCommand(
            orderId: $order,
            status: $d['status'],
            changedBy: (string) $request->user()->getAuthIdentifier(),
            actorCarrierId: DriverScope::carrierId($request) ?? $request->user()->carrier_id,
            latitude: isset($d['latitude']) ? (float) $d['latitude'] : null,
            longitude: isset($d['longitude']) ? (float) $d['longitude'] : null,
            note: $d['note'] ?? null,
            incidentReason: $d['incident_reason'] ?? null,
        ));

        return back()->with('success', $d['status'] === 'in_route' ? 'Vas en camino. Le avisamos al cliente.' : 'Novedad registrada. Despacho ya la puede ver.');
    }

    public function deliver(DeliverOrderRequest $request, string $order, DeliverOrderHandler $deliver, GetOrderHandler $getOrder): RedirectResponse
    {
        $this->ownOrder($request, $order, $getOrder);
        $done = $deliver($request->toCommand($order));

        return redirect()->route('driver.home')->with('success', "Entrega de {$done->trackingCode()->value()} registrada. ¡Bien hecho!");
    }

    /** Ubicación del celular mientras la app está abierta (el mapa en vivo la muestra). */
    public function location(Request $request, ReportLocationHandler $report): JsonResponse
    {
        $d = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $carrier = $request->user()->carrier_id;
        abort_if($carrier === null, 422, 'Tu usuario no está vinculado a un transportador.');
        $report($carrier, (float) $d['latitude'], (float) $d['longitude']);

        return new JsonResponse(status: 204);
    }

    /** El pedido debe estar asignado al transportador del usuario; si no, 404 (no se revela que existe). */
    private function ownOrder(Request $request, string $order, GetOrderHandler $getOrder): Order
    {
        $carrier = $request->user()->carrier_id;
        $found = $getOrder($order);
        if ($carrier === null || ! $found->isAssignedTo(CarrierId::fromString($carrier))) {
            throw new OrderNotFound($order);
        }

        return $found;
    }
}
