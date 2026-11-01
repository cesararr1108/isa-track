<?php

declare(strict_types=1);

namespace Isa\Routing\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Isa\Routing\Domain\Exception\DuplicateStop;
use Isa\Routing\Domain\Exception\InvalidRouteStatusChange;
use Isa\Routing\Domain\Exception\RouteNotEditable;
use Isa\Routing\Domain\Exception\RouteRequiresStops;
use Isa\Routing\Domain\Exception\StopsMismatch;
use Isa\Shared\Domain\AggregateRoot;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;

/**
 * Ruta programada: un mensajero, un día y una lista ordenada de pedidos
 * que salen desde un punto de partida (la bodega).
 */
final class DeliveryRoute extends AggregateRoot
{
    /** @param list<RouteStop> $stops */
    private function __construct(
        private readonly RouteId $id,
        private readonly CompanyId $companyId,
        private readonly CarrierId $carrierId,
        private readonly string $date,
        private string $name,
        private readonly GeoPoint $start,
        private array $stops,
        private RouteStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $startedAt = null,
        private ?DateTimeImmutable $finishedAt = null,
    ) {}

    /** @param list<RouteStop> $stops */
    public static function plan(
        RouteId $id,
        CompanyId $companyId,
        CarrierId $carrierId,
        string $date,
        string $name,
        GeoPoint $start,
        array $stops,
        LoadLimit $limit,
        DateTimeImmutable $now,
        ?RouteOptimizer $optimizer = null,
    ): self {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException(sprintf('<%s> no es una fecha válida (AAAA-MM-DD).', $date));
        }
        if (trim($name) === '') {
            throw new InvalidArgumentException('La ruta necesita un nombre.');
        }
        self::guardStops($stops);
        $limit->assertFits($stops);

        $stops = $optimizer?->optimize($start, array_values($stops)) ?? array_values($stops);

        return new self($id, $companyId, $carrierId, $date, trim($name), $start, $stops, RouteStatus::Planned, $now);
    }

    /** @param list<RouteStop> $stops */
    public static function restore(
        RouteId $id,
        CompanyId $companyId,
        CarrierId $carrierId,
        string $date,
        string $name,
        GeoPoint $start,
        array $stops,
        RouteStatus $status,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
    ): self {
        return new self($id, $companyId, $carrierId, $date, $name, $start, $stops, $status, $createdAt, $startedAt, $finishedAt);
    }

    /** Cambia el orden de las paradas (el despachador las arrastra en el mapa). */
    public function reorder(array $orderIds): void
    {
        $this->guardEditable();
        $current = array_map(fn (RouteStop $s) => $s->orderId, $this->stops);
        $sortedNew = $orderIds;
        $sortedOld = $current;
        sort($sortedNew);
        sort($sortedOld);
        if ($sortedNew !== $sortedOld) {
            throw new StopsMismatch;
        }

        $byId = [];
        foreach ($this->stops as $stop) {
            $byId[$stop->orderId] = $stop;
        }
        $this->stops = array_map(fn (string $id) => $byId[$id], array_values($orderIds));
    }

    public function optimize(RouteOptimizer $optimizer): void
    {
        $this->guardEditable();
        $this->stops = $optimizer->optimize($this->start, $this->stops);
    }

    public function changeStatus(RouteStatus $to, DateTimeImmutable $at): void
    {
        if (! in_array($to, $this->status->allowedNext(), true)) {
            throw new InvalidRouteStatusChange($this->status->value, $to->value);
        }
        if ($to === RouteStatus::InProgress) {
            $this->startedAt = $at;
        } else {
            $this->finishedAt = $at;
        }
        $this->status = $to;
    }

    /** Kilómetros en línea recta: bodega → parada 1 → ... → última parada. */
    public function distanceKm(): float
    {
        return RouteOptimizer::lengthKm($this->start, $this->stops);
    }

    public function totalWeightKg(): float
    {
        return (float) array_sum(array_map(fn (RouteStop $s) => $s->weightKg, $this->stops));
    }

    public function totalPackages(): int
    {
        return (int) array_sum(array_map(fn (RouteStop $s) => $s->packages, $this->stops));
    }

    /** @param list<RouteStop> $stops */
    private static function guardStops(array $stops): void
    {
        if ($stops === []) {
            throw new RouteRequiresStops;
        }
        $seen = [];
        foreach ($stops as $stop) {
            if (isset($seen[$stop->orderId])) {
                throw new DuplicateStop($stop->orderId);
            }
            $seen[$stop->orderId] = true;
        }
    }

    private function guardEditable(): void
    {
        if ($this->status !== RouteStatus::Planned) {
            throw new RouteNotEditable;
        }
    }

    public function id(): RouteId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function carrierId(): CarrierId
    {
        return $this->carrierId;
    }

    public function date(): string
    {
        return $this->date;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function start(): GeoPoint
    {
        return $this->start;
    }

    /** @return list<RouteStop> */
    public function stops(): array
    {
        return $this->stops;
    }

    /** @return list<string> */
    public function orderIds(): array
    {
        return array_map(fn (RouteStop $s) => $s->orderId, $this->stops);
    }

    public function status(): RouteStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
