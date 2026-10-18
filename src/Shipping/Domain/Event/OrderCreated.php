<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Event;

use DateTimeImmutable;
use Isa\Shared\Domain\DomainEvent;

final class OrderCreated implements DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $companyId,
        public readonly string $trackingCode,
        private readonly DateTimeImmutable $occurredOn,
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
