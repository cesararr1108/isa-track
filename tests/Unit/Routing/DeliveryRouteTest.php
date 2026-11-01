<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use DateTimeImmutable;
use Isa\Routing\Domain\DeliveryRoute;
use Isa\Routing\Domain\Exception\DuplicateStop;
use Isa\Routing\Domain\Exception\InvalidRouteStatusChange;
use Isa\Routing\Domain\Exception\RouteExceedsCapacity;
use Isa\Routing\Domain\Exception\RouteNotEditable;
use Isa\Routing\Domain\Exception\RouteRequiresStops;
use Isa\Routing\Domain\Exception\StopsMismatch;
use Isa\Routing\Domain\LoadLimit;
use Isa\Routing\Domain\RouteId;
use Isa\Routing\Domain\RouteOptimizer;
use Isa\Routing\Domain\RouteStatus;
use Isa\Routing\Domain\RouteStop;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use PHPUnit\Framework\TestCase;

final class DeliveryRouteTest extends TestCase
{
    public function test_optimizer_visits_closest_stops_first(): void
    {
        $start = new GeoPoint(6.20, -75.58);
        // En línea: 6.21, 6.22, 6.23, 6.24 al norte de la bodega, desordenadas.
        $stops = [$this->stop('d', 6.24), $this->stop('b', 6.22), $this->stop('a', 6.21), $this->stop('c', 6.23)];

        $ordered = (new RouteOptimizer)->optimize($start, $stops);

        self::assertSame(['a', 'b', 'c', 'd'], array_map(fn (RouteStop $s) => $s->orderId, $ordered));
        self::assertLessThan(RouteOptimizer::lengthKm($start, $stops), RouteOptimizer::lengthKm($start, $ordered));
    }

    public function test_two_opt_untangles_crossing_paths(): void
    {
        $start = new GeoPoint(0, 0);
        $stops = [
            new RouteStop('a', new GeoPoint(0, 1)),
            new RouteStop('b', new GeoPoint(1, 1)),
            new RouteStop('c', new GeoPoint(1, 0)),
            new RouteStop('d', new GeoPoint(0.5, 0.5)),
        ];
        $ordered = (new RouteOptimizer)->optimize($start, $stops);
        $nn = RouteOptimizer::lengthKm($start, $stops);

        self::assertLessThanOrEqual($nn, RouteOptimizer::lengthKm($start, $ordered));
        self::assertCount(4, $ordered);
    }

    public function test_plan_checks_capacity_duplicates_and_empty(): void
    {
        $stops = [$this->stop('a', 6.21, 30), $this->stop('b', 6.22, 15)];

        try {
            $this->plan($stops, new LoadLimit(40));
            self::fail('Debía rechazar por peso');
        } catch (RouteExceedsCapacity $e) {
            self::assertStringContainsString('45 kg de 40 kg', $e->getMessage());
        }

        try {
            $this->plan($stops, new LoadLimit(null, 1));
            self::fail('Debía rechazar por paquetes');
        } catch (RouteExceedsCapacity) {
        }

        try {
            $this->plan([$this->stop('a', 6.21), $this->stop('a', 6.22)]);
            self::fail('Debía rechazar duplicados');
        } catch (DuplicateStop) {
        }

        $this->expectException(RouteRequiresStops::class);
        $this->plan([]);
    }

    public function test_reorder_requires_same_orders_and_planned_status(): void
    {
        $route = $this->plan([$this->stop('a', 6.21), $this->stop('b', 6.22)]);
        $route->reorder(['b', 'a']);
        self::assertSame(['b', 'a'], $route->orderIds());

        try {
            $route->reorder(['a', 'c']);
            self::fail('Debía rechazar pedidos distintos');
        } catch (StopsMismatch) {
        }

        $route->changeStatus(RouteStatus::InProgress, new DateTimeImmutable);
        self::assertNotNull($route->startedAt());

        $this->expectException(RouteNotEditable::class);
        $route->reorder(['a', 'b']);
    }

    public function test_status_flow(): void
    {
        $route = $this->plan([$this->stop('a', 6.21)]);
        $route->changeStatus(RouteStatus::InProgress, new DateTimeImmutable);
        $route->changeStatus(RouteStatus::Completed, new DateTimeImmutable);
        self::assertNotNull($route->finishedAt());

        $this->expectException(InvalidRouteStatusChange::class);
        $route->changeStatus(RouteStatus::InProgress, new DateTimeImmutable);
    }

    public function test_totals(): void
    {
        $route = $this->plan([$this->stop('a', 6.21, 2.5, 2), $this->stop('b', 6.22, 1, 1)]);

        self::assertSame(3.5, $route->totalWeightKg());
        self::assertSame(3, $route->totalPackages());
        self::assertEqualsWithDelta(2.22, $route->distanceKm(), 0.05);
    }

    private function stop(string $id, float $lat, float $kg = 1, int $packages = 1): RouteStop
    {
        return new RouteStop($id, new GeoPoint($lat, -75.58), $kg, $packages);
    }

    /** @param list<RouteStop> $stops */
    private function plan(array $stops, ?LoadLimit $limit = null): DeliveryRoute
    {
        return DeliveryRoute::plan(
            RouteId::generate(), CompanyId::generate(), CarrierId::generate(), '2026-11-02', 'Ruta 1',
            new GeoPoint(6.20, -75.58), $stops, $limit ?? new LoadLimit, new DateTimeImmutable,
        );
    }
}
