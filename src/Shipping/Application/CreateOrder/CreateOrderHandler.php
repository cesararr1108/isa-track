<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\CreateOrder;

use DateTimeImmutable;
use Isa\Shared\Application\EventBus;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shared\Domain\ValueObject\Money;
use Isa\Shared\Domain\ValueObject\UserId;
use Isa\Shipping\Domain\Address;
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Domain\OrderId;
use Isa\Shipping\Domain\OrderRepository;
use Isa\Shipping\Domain\Recipient;
use Isa\Shipping\Domain\TrackingCode;
use Isa\Shipping\Domain\TrackingCodeGenerator;
use RuntimeException;

final class CreateOrderHandler
{
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly TrackingCodeGenerator $codes,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
        private readonly EventBus $events,
    ) {}

    public function __invoke(CreateOrderCommand $command): Order
    {
        $order = Order::create(
            OrderId::generate(),
            $this->tenant->companyId(),
            $this->uniqueTrackingCode(),
            $command->externalReference,
            new Recipient($command->recipientName, $command->recipientPhone, $command->recipientEmail),
            new Address(
                $command->addressLine,
                $command->city,
                $command->neighborhood,
                $command->addressReference,
                GeoPoint::fromNullable($command->latitude, $command->longitude),
            ),
            $command->packages,
            $command->promisedAt !== null ? new DateTimeImmutable($command->promisedAt) : null,
            $this->clock->now(),
            $command->createdBy !== null ? UserId::fromString($command->createdBy) : null,
            $command->cashOnDelivery !== null ? new Money($command->cashOnDelivery) : null,
        );

        $this->orders->save($order);
        $this->events->publish(...$order->pullDomainEvents());

        return $order;
    }

    private function uniqueTrackingCode(): TrackingCode
    {
        for ($i = 0; $i < self::MAX_CODE_ATTEMPTS; $i++) {
            $code = $this->codes->generate();
            if (! $this->orders->trackingCodeExists($code)) {
                return $code;
            }
        }

        throw new RuntimeException('No se pudo generar un código de rastreo único.');
    }
}
