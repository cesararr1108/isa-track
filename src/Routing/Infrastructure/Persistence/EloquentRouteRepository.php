<?php

declare(strict_types=1);

namespace Isa\Routing\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Isa\Routing\Domain\DeliveryRoute;
use Isa\Routing\Domain\RouteId;
use Isa\Routing\Domain\RouteRepository;
use Isa\Routing\Domain\RouteStatus;
use Isa\Routing\Domain\RouteStop;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;

final class EloquentRouteRepository implements RouteRepository
{
    public function save(DeliveryRoute $route): void
    {
        DB::transaction(function () use ($route): void {
            RouteModel::query()->updateOrCreate(['id' => $route->id()->value()], [
                'company_id' => $route->companyId()->value(),
                'carrier_id' => $route->carrierId()->value(),
                'route_date' => $route->date(),
                'name' => $route->name(),
                'status' => $route->status()->value,
                'start_latitude' => $route->start()->latitude,
                'start_longitude' => $route->start()->longitude,
                'distance_km' => round($route->distanceKm(), 2),
                'started_at' => $route->startedAt(),
                'finished_at' => $route->finishedAt(),
                'created_at' => $route->createdAt(),
            ]);

            // Las paradas se reemplazan completas: el orden es lo que cambia.
            RouteStopModel::query()->where('route_id', $route->id()->value())->delete();
            foreach ($route->stops() as $i => $stop) {
                RouteStopModel::query()->create([
                    'route_id' => $route->id()->value(),
                    'order_id' => $stop->orderId,
                    'sequence' => $i + 1,
                    'latitude' => $stop->location->latitude,
                    'longitude' => $stop->location->longitude,
                    'weight_kg' => $stop->weightKg,
                    'packages' => $stop->packages,
                ]);
            }
        });
    }

    public function find(CompanyId $companyId, RouteId $id): ?DeliveryRoute
    {
        $row = RouteModel::query()->with('stops')->where('company_id', $companyId->value())->find($id->value());

        return $row === null ? null : $this->toDomain($row);
    }

    public function search(CompanyId $companyId, array $filters): array
    {
        return RouteModel::query()
            ->with('stops')
            ->where('company_id', $companyId->value())
            ->when($filters['date'] ?? null, fn ($q, $d) => $q->whereDate('route_date', $d))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('route_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('route_date', '<=', $d))
            ->when($filters['carrier_id'] ?? null, fn ($q, $c) => $q->where('carrier_id', $c))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('route_date')
            ->orderBy('name')
            ->get()
            ->map(fn (RouteModel $r) => $this->toDomain($r))
            ->all();
    }

    public function ordersInOpenRoutes(CompanyId $companyId, array $orderIds, ?RouteId $except = null): array
    {
        if ($orderIds === []) {
            return [];
        }

        return RouteStopModel::query()
            ->join('delivery_routes', 'delivery_routes.id', '=', 'delivery_route_stops.route_id')
            ->where('delivery_routes.company_id', $companyId->value())
            ->whereIn('delivery_routes.status', [RouteStatus::Planned->value, RouteStatus::InProgress->value])
            ->whereIn('delivery_route_stops.order_id', $orderIds)
            ->when($except, fn ($q) => $q->where('delivery_routes.id', '!=', $except->value()))
            ->pluck('delivery_route_stops.order_id')
            ->unique()
            ->values()
            ->all();
    }

    private function toDomain(RouteModel $row): DeliveryRoute
    {
        return DeliveryRoute::restore(
            RouteId::fromString($row->id),
            CompanyId::fromString($row->company_id),
            CarrierId::fromString($row->carrier_id),
            substr((string) $row->route_date, 0, 10),
            $row->name,
            new GeoPoint($row->start_latitude, $row->start_longitude),
            $row->stops->map(fn (RouteStopModel $s) => new RouteStop(
                $s->order_id,
                new GeoPoint($s->latitude, $s->longitude),
                $s->weight_kg,
                $s->packages,
            ))->all(),
            RouteStatus::from($row->status),
            $row->created_at ?? new DateTimeImmutable,
            $row->started_at,
            $row->finished_at,
        );
    }
}
