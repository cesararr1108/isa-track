<?php

declare(strict_types=1);

namespace Isa\Cash\Application\CloseCashRegister;

final class CloseCashRegisterCommand
{
    public function __construct(
        public readonly string $carrierId,
        public readonly string $date,
        public readonly int $cashReceived,
        public readonly ?string $notes = null,
        public readonly ?string $closedBy = null,
    ) {}
}
