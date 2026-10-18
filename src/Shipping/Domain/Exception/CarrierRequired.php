<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class CarrierRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('El pedido no tiene transportador asignado.');
    }

    public function errorCode(): string
    {
        return 'carrier_required';
    }
}
