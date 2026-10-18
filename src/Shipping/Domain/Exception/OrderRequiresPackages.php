<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class OrderRequiresPackages extends DomainException
{
    public function __construct()
    {
        parent::__construct('El pedido debe tener al menos un paquete.');
    }

    public function errorCode(): string
    {
        return 'order_requires_packages';
    }
}
