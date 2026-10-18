<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\GetOrder;

use Isa\Shared\Application\TenantContext;
use Isa\Shipping\Domain\Exception\OrderNotFound;
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Domain\OrderId;
use Isa\Shipping\Domain\OrderRepository;

final class GetOrderHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly TenantContext $tenant,
    ) {}

    public function __invoke(string $orderId): Order
    {
        return $this->orders->find($this->tenant->companyId(), OrderId::fromString($orderId))
            ?? throw new OrderNotFound($orderId);
    }
}
