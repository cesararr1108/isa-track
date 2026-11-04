<?php

declare(strict_types=1);

namespace Isa\Routing\Infrastructure\Http\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Routing\Application\ChangeRouteStatus\ChangeRouteStatusHandler;
use Isa\Routing\Application\GetRoute\GetRouteHandler;
use Isa\Routing\Application\ListRoutableOrders\ListRoutableOrdersHandler;
use Isa\Routing\Application\ListRoutes\ListRoutesHandler;
use Isa\Routing\Application\OptimizeRoute\OptimizeRouteHandler;
use Isa\Routing\Application\PlanRoute\PlanRouteCommand;
use Isa\Routing\Application\PlanRoute\PlanRouteHandler;
use Isa\Routing\Application\ReorderStops\ReorderStopsHandler;
use Isa\Routing\Domain\Exception\RouteNotFound;
use Isa\Routing\Domain\RouteStatus;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Infrastructure\Auth\DriverScope;
use Isa\Shared\Infrastructure\Auth\PermissionChecker;

/** Programación de rutas en el panel: planear, ver en el mapa, reordenar e iniciar. */
final class RoutePageController
{
    public function __construct(private readonly Clock $clock) {}

    public function index(
        Request $request,
        ListRoutesHandler $list,
        GetRouteHandler $getRoute,
        ListRoutableOrdersHandler $routable,
        ListCarriersHandler $carriers,
        PermissionChecker $permissions,
    ): Response {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'route' => ['nullable', 'uuid']]);
        $date = $data['date'] ?? $this->clock->now()->format('Y-m-d');
        $driver = DriverScope::carrierId($request);
        $user = $request->user();
        $canManage = $permissions->allows($user->role, 'routes.manage', $user->permissions ?? [], $user->revoked_permissions ?? []);

        $routes = $list(['date' => $date, 'carrier_id' => $driver]);
        $selected = collect($routes)->firstWhere('id', $data['route'] ?? null) ?? ($routes[0] ?? null);

        return Inertia::render('routes/pages/RoutesIndex', [
            'date' => $date,
            'routes' => $routes,
            'selected' => $selected,
            'routable' => $canManage ? $routable() : [],
            'carriers' => $canManage ? array_values(array_map(fn (array $c) => [
                'id' => $c['id'], 'name' => $c['name'], 'vehicle_type' => $c['vehicle_type'], 'vehicle_plate' => $c['vehicle_plate'],
                'capacity_kg' => $c['capacity_kg'], 'capacity_packages' => $c['capacity_packages'],
            ], $carriers(onlyActive: true))) : [],
            'statuses' => config('isa.statuses'),
            'vehicleTypes' => config('isa.fleet.vehicle_labels'),
        ]);
    }

    public function store(Request $request, PlanRouteHandler $plan): RedirectResponse
    {
        $d = $request->validate([
            'carrier_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'name' => ['nullable', 'string', 'max:120'],
            'order_ids' => ['required', 'array', 'min:1', 'max:200'],
            'order_ids.*' => ['required', 'uuid', 'distinct'],
            'optimize' => ['nullable', 'boolean'],
        ], ['order_ids.required' => 'Elige al menos un pedido.', 'date.after_or_equal' => 'No se pueden programar rutas en días pasados.'], ['carrier_id' => 'mensajero', 'date' => 'día']);

        $route = $plan(new PlanRouteCommand(
            carrierId: $d['carrier_id'],
            date: $d['date'],
            orderIds: array_values($d['order_ids']),
            name: $d['name'] ?? null,
            optimize: $request->boolean('optimize', true),
            plannedBy: (string) $request->user()->getAuthIdentifier(),
        ));

        return redirect()->route('panel.routes.index', ['date' => $route->date(), 'route' => $route->id()->value()])
            ->with('success', 'Ruta programada. Los pedidos quedaron asignados al mensajero.');
    }

    public function reorder(Request $request, string $route, ReorderStopsHandler $reorder): RedirectResponse
    {
        $d = $request->validate(['order_ids' => ['required', 'array', 'min:1'], 'order_ids.*' => ['required', 'uuid', 'distinct']]);
        $reorder($route, array_values($d['order_ids']));

        return back();
    }

    public function optimize(string $route, OptimizeRouteHandler $optimize, GetRouteHandler $getRoute): RedirectResponse
    {
        $before = $getRoute->load($route)->distanceKm();
        $after = $optimize($route)->distanceKm();

        return back()->with('success', sprintf('Orden optimizado: %.1f → %.1f km.', $before, $after));
    }

    public function changeStatus(Request $request, string $route, ChangeRouteStatusHandler $change, GetRouteHandler $getRoute): RedirectResponse
    {
        $d = $request->validate(['status' => ['required', Rule::enum(RouteStatus::class)]]);
        $driver = DriverScope::carrierId($request);
        if ($driver !== null && $getRoute->load($route)->carrierId()->value() !== $driver) {
            throw new RouteNotFound($route);
        }
        $change($route, $d['status'], $driver);

        return back()->with('success', ['in_progress' => 'Ruta iniciada.', 'completed' => 'Ruta completada.', 'cancelled' => 'Ruta cancelada. Los pedidos quedan asignados y se pueden programar en otra ruta.'][$d['status']] ?? 'Ruta actualizada.');
    }
}
