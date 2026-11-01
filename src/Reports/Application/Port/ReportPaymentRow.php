<?php

declare(strict_types=1);

namespace Isa\Reports\Application\Port;

final class ReportPaymentRow
{
    public function __construct(
        public readonly ?string $carrierId,
        public readonly string $method,
        public readonly int $amount,
        public readonly string $collectedOn,
    ) {}
}
