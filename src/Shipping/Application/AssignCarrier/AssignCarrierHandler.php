<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\AssignCarrier;

use Isa\Shared\Application\EventBus;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Application\TransactionManager;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\UserId;
use Isa\Shipping\Application\Port\CarrierDirectory;
use Isa\Shipping\Domain\Exception\CarrierNotAvailable;
use Isa\Shipping\Domain\Exception\OrderNotFound;
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Domain\OrderId;
use Isa\Shipping\Domain\OrderRepository;
use Isa\Shipping\Domain\StatusTransitionPolicy;

final class AssignCarrierHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly CarrierDirectory $carriers,
        private readonly StatusTransitionPolicy $transitions,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
        private readonly TransactionManager $transaction,
        private readonly EventBus $events,
    ) {}

    public function __invoke(AssignCarrierCommand $command): Order
    {
        $companyId = $this->tenant->companyId();
        $carrierId = CarrierId::fromString($command->carrierId);

        if (! $this->carriers->isAvailable($companyId, $carrierId)) {
            throw new CarrierNotAvailable;
        }

        $order = $this->transaction->run(function () use ($command, $companyId, $carrierId) {
            $order = $this->orders->find($companyId, OrderId::fromString($command->orderId))
                ?? throw new OrderNotFound($command->orderId);

            $order->assignCarrier(
                $carrierId,
                $this->transitions,
                $this->clock->now(),
                $command->assignedBy !== null ? UserId::fromString($command->assignedBy) : null,
            );
            $this->orders->save($order);

            return $order;
        });

        $this->events->publish(...$order->pullDomainEvents());

        return $order;
    }
}
