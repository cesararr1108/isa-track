<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\RegisterCarrier;

final class RegisterCarrierCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $phone = null,
        public readonly ?string $vehiclePlate = null,
    ) {}
}
