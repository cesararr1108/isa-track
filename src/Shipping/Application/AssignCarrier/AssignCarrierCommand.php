<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\AssignCarrier;

final class AssignCarrierCommand
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $carrierId,
        public readonly ?string $assignedBy = null,
    ) {}
}
