<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class OrderNotAssignedToActor extends DomainException
{
    public function __construct()
    {
        parent::__construct('Este pedido no está asignado a tu usuario.');
    }

    public function errorCode(): string
    {
        return 'order_not_assigned_to_actor';
    }
}
