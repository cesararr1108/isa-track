<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use DateTimeImmutable;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shipping\Domain\Address;
use Isa\Shipping\Domain\Event\OrderCreated;
use Isa\Shipping\Domain\Event\OrderStatusChanged;
use Isa\Shipping\Domain\Exception\CarrierRequired;
use Isa\Shipping\Domain\Exception\IncidentReasonRequired;
use Isa\Shipping\Domain\Exception\InvalidStatusTransition;
use Isa\Shipping\Domain\Exception\OrderRequiresPackages;
use Isa\Shipping\Domain\IncidentReason;
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Domain\OrderId;
use Isa\Shipping\Domain\OrderStatus;
use Isa\Shipping\Domain\Recipient;
use Isa\Shipping\Domain\TrackingCode;
use Isa\Shipping\Domain\TransitionMap;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas del dominio puro: no arrancan Laravel ni tocan la base de datos,
 * por eso son instantáneas.
 */
final class OrderTest extends TestCase
{
    private TransitionMap $policy;

    protected function setUp(): void
    {
        $this->policy = new TransitionMap([
            'created' => ['prepared', 'assigned', 'returned'],
            'prepared' => ['assigned'],
            'assigned' => ['in_route'],
            'in_route' => ['delivered', 'incident'],
            'incident' => ['in_route', 'returned'],
        ]);
    }

    public function test_creates_order_with_packages_history_and_events(): void
    {
        $order = $this->newOrder();

        self::assertSame(OrderStatus::Created, $order->status());
        self::assertSame(['ISA-ABC123-1', 'ISA-ABC123-2'], array_map(fn ($p) => $p->code, $order->packages()));
        self::assertCount(1, $order->history());

        $events = $order->pullDomainEvents();
        self::assertInstanceOf(OrderCreated::class, $events[0]);
        self::assertInstanceOf(OrderStatusChanged::class, $events[1]);
        self::assertSame([], $order->pullDomainEvents(), 'Los eventos se entregan una sola vez');
    }

    public function test_order_requires_at_least_one_package(): void
    {
        $this->expectException(OrderRequiresPackages::class);

        $this->newOrder(packages: []);
    }

    public function test_full_happy_path(): void
    {
        $order = $this->newOrder();
        $carrier = CarrierId::generate();

        $order->assignCarrier($carrier, $this->policy, $this->at('10:00'), null);
        $order->changeStatus(OrderStatus::InRoute, $this->policy, $this->at('11:00'), null, new GeoPoint(6.2, -75.5));
        $order->changeStatus(OrderStatus::Delivered, $this->policy, $this->at('12:00'), null);

        self::assertSame(OrderStatus::Delivered, $order->status());
        self::assertTrue($order->isAssignedTo($carrier));
        self::assertSame(
            ['created', 'assigned', 'in_route', 'delivered'],
            array_map(fn ($h) => $h->status->value, $order->history()),
        );
        self::assertEqualsWithDelta(6.2, $order->lastKnownLocation()?->latitude, 0.0001);
    }

    public function test_rejects_transitions_not_allowed_by_policy(): void
    {
        $order = $this->newOrder();

        $this->expectException(InvalidStatusTransition::class);
        $order->changeStatus(OrderStatus::Delivered, $this->policy, $this->at('10:00'), null);
    }

    public function test_cannot_assign_status_without_carrier(): void
    {
        $order = $this->newOrder();

        $this->expectException(CarrierRequired::class);
        $order->changeStatus(OrderStatus::Assigned, $this->policy, $this->at('10:00'), null);
    }

    public function test_incident_requires_reason(): void
    {
        $order = $this->inRouteOrder();

        $this->expectException(IncidentReasonRequired::class);
        $order->changeStatus(OrderStatus::Incident, $this->policy, $this->at('12:00'), null);
    }

    public function test_incident_with_reason_is_recorded(): void
    {
        $order = $this->inRouteOrder();

        $order->changeStatus(OrderStatus::Incident, $this->policy, $this->at('12:00'), null, note: 'No abren', incidentReason: IncidentReason::CustomerAbsent);

        $last = $order->history()[array_key_last($order->history())];
        self::assertSame(IncidentReason::CustomerAbsent, $last->incidentReason);
        self::assertSame('No abren', $last->note);
    }

    public function test_reassigning_keeps_status_and_adds_history(): void
    {
        $order = $this->newOrder();
        $order->assignCarrier(CarrierId::generate(), $this->policy, $this->at('10:00'), null);
        $other = CarrierId::generate();

        $order->assignCarrier($other, $this->policy, $this->at('10:30'), null);

        self::assertSame(OrderStatus::Assigned, $order->status());
        self::assertTrue($order->isAssignedTo($other));
        self::assertSame('Reasignado', $order->history()[2]->note);
    }

    private function inRouteOrder(): Order
    {
        $order = $this->newOrder();
        $order->assignCarrier(CarrierId::generate(), $this->policy, $this->at('10:00'), null);
        $order->changeStatus(OrderStatus::InRoute, $this->policy, $this->at('11:00'), null);

        return $order;
    }

    /** @param list<array{description: string}>|null $packages */
    private function newOrder(?array $packages = null): Order
    {
        return Order::create(
            OrderId::generate(),
            CompanyId::generate(),
            new TrackingCode('ISA-ABC123'),
            'WEB-1',
            new Recipient('Laura Gómez', '+57 300 000 0000'),
            new Address('Cra 43A # 1-50', 'Medellín', 'El Poblado'),
            $packages ?? [['description' => 'Caja'], ['description' => 'Sobre']],
            null,
            $this->at('09:00'),
            null,
        );
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-18 '.$time);
    }
}
