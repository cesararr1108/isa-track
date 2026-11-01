<?php

declare(strict_types=1);

namespace Isa\Routing\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Isa\Routing\Application\ChangeRouteStatus\ChangeRouteStatusHandler;
use Isa\Routing\Application\GetRoute\GetRouteHandler;
use Isa\Routing\Application\ListRoutes\ListRoutesHandler;
use Isa\Routing\Application\OptimizeRoute\OptimizeRouteHandler;
use Isa\Routing\Application\PlanRoute\PlanRouteCommand;
use Isa\Routing\Application\PlanRoute\PlanRouteHandler;
use Isa\Routing\Application\ReorderStops\ReorderStopsHandler;
use Isa\Routing\Domain\Exception\RouteNotFound;
use Isa\Routing\Domain\RouteStatus;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Infrastructure\Auth\DriverScope;

/** Programación de rutas por mensajero y día. */
final class RouteController
{
    public function index(Request $request, ListRoutesHandler $listRoutes): JsonResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'carrier_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::enum(RouteStatus::class)],
        ]);
        // El mensajero solo ve sus rutas.
        $filters['carrier_id'] = DriverScope::carrierId($request) ?? ($filters['carrier_id'] ?? null);

        return new JsonResponse(['data' => $listRoutes($filters)]);
    }

    public function store(Request $request, PlanRouteHandler $planRoute, GetRouteHandler $getRoute, Clock $clock): JsonResponse
    {
        $data = $request->validate([
            'carrier_id' => ['required', 'uuid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'name' => ['nullable', 'string', 'max:120'],
            'order_ids' => ['required', 'array', 'min:1', 'max:200'],
            'order_ids.*' => ['required', 'uuid', 'distinct'],
            'optimize' => ['nullable', 'boolean'],
            'start.latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:start.longitude'],
            'start.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:start.latitude'],
        ]);

        $route = $planRoute(new PlanRouteCommand(
            carrierId: $data['carrier_id'],
            date: $data['date'] ?? $clock->now()->format('Y-m-d'),
            orderIds: array_values($data['order_ids']),
            name: $data['name'] ?? null,
            optimize: $request->has('optimize') ? $request->boolean('optimize') : true,
            startLatitude: isset($data['start']['latitude']) ? (float) $data['start']['latitude'] : null,
            startLongitude: isset($data['start']['longitude']) ? (float) $data['start']['longitude'] : null,
            plannedBy: (string) $request->user()->getAuthIdentifier(),
        ));

        return new JsonResponse(['data' => $getRoute->present($route)], 201);
    }

    public function show(Request $request, string $route, GetRouteHandler $getRoute): JsonResponse
    {
        $loaded = $getRoute->load($route);
        $this->guardDriver($request, $loaded->carrierId()->value(), $route);

        return new JsonResponse(['data' => $getRoute->present($loaded)]);
    }

    /** GET /api/routes/{id}/geojson: la ruta lista para pintar en el mapa. */
    public function geoJson(Request $request, string $route, GetRouteHandler $getRoute): JsonResponse
    {
        $this->guardDriver($request, $getRoute->load($route)->carrierId()->value(), $route);

        return new JsonResponse($getRoute->geoJson($route));
    }

    public function reorder(Request $request, string $route, ReorderStopsHandler $reorder, GetRouteHandler $getRoute): JsonResponse
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'uuid', 'distinct'],
        ]);

        return new JsonResponse(['data' => $getRoute->present($reorder($route, array_values($data['order_ids'])))]);
    }

    public function optimize(string $route, OptimizeRouteHandler $optimize, GetRouteHandler $getRoute): JsonResponse
    {
        return new JsonResponse(['data' => $getRoute->present($optimize($route))]);
    }

    public function changeStatus(Request $request, string $route, ChangeRouteStatusHandler $change, GetRouteHandler $getRoute): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(RouteStatus::class)]]);

        return new JsonResponse(['data' => $getRoute->present(
            $change($route, $data['status'], DriverScope::carrierId($request)),
        )]);
    }

    private function guardDriver(Request $request, string $routeCarrierId, string $routeId): void
    {
        $driver = DriverScope::carrierId($request);
        if ($driver !== null && $driver !== $routeCarrierId) {
            throw new RouteNotFound($routeId);
        }
    }
}
