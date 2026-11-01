<?php

declare(strict_types=1);

namespace Isa\Reports\Application\Port;

final class ReportIncidentRow
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $carrierId,
        public readonly string $occurredOn,
    ) {}
}
