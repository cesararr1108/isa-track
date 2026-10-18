<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\ChangeOrderStatus;

use Isa\Shared\Application\EventBus;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Application\TransactionManager;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shared\Domain\ValueObject\UserId;
use Isa\Shipping\Domain\Exception\OrderNotAssignedToActor;
use Isa\Shipping\Domain\Exception\OrderNotFound;
use Isa\Shipping\Domain\IncidentReason;
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Domain\OrderId;
use Isa\Shipping\Domain\OrderRepository;
use Isa\Shipping\Domain\OrderStatus;
use Isa\Shipping\Domain\StatusTransitionPolicy;

final class ChangeOrderStatusHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly StatusTransitionPolicy $transitions,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
        private readonly TransactionManager $transaction,
        private readonly EventBus $events,
    ) {}

    public function __invoke(ChangeOrderStatusCommand $command): Order
    {
        $order = $this->transaction->run(function () use ($command) {
            $order = $this->orders->find($this->tenant->companyId(), OrderId::fromString($command->orderId))
                ?? throw new OrderNotFound($command->orderId);

            if ($command->actorCarrierId !== null && ! $order->isAssignedTo(CarrierId::fromString($command->actorCarrierId))) {
                throw new OrderNotAssignedToActor;
            }

            $order->changeStatus(
                OrderStatus::from($command->status),
                $this->transitions,
                $this->clock->now(),
                $command->changedBy !== null ? UserId::fromString($command->changedBy) : null,
                GeoPoint::fromNullable($command->latitude, $command->longitude),
                $command->note,
                $command->incidentReason !== null ? IncidentReason::from($command->incidentReason) : null,
            );
            $this->orders->save($order);

            return $order;
        });

        $this->events->publish(...$order->pullDomainEvents());

        return $order;
    }
}
