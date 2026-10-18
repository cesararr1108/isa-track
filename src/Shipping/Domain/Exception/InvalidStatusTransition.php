<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;
use Isa\Shipping\Domain\OrderStatus;

final class InvalidStatusTransition extends DomainException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct(sprintf('No se puede pasar el pedido de "%s" a "%s".', $from->value, $to->value));
    }

    public function errorCode(): string
    {
        return 'invalid_status_transition';
    }
}
