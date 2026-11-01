<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class OrderNotRoutable extends DomainException
{
    public function __construct(string $order, string $status)
    {
        parent::__construct(sprintf('El pedido %s no se puede programar en una ruta (estado: %s).', $order, $status));
    }

    public function errorCode(): string
    {
        return 'order_not_routable';
    }
}
