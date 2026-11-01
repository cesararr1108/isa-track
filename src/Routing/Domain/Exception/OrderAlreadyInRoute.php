<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class OrderAlreadyInRoute extends DomainException
{
    public function __construct(string $order)
    {
        parent::__construct(sprintf('El pedido %s ya está en otra ruta abierta.', $order));
    }

    public function errorCode(): string
    {
        return 'order_already_in_route';
    }
}
