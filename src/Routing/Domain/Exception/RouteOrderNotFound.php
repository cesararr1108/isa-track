<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\NotFoundException;

final class RouteOrderNotFound extends NotFoundException
{
    public function __construct(string $orderId)
    {
        parent::__construct(sprintf('No existe el pedido <%s>.', $orderId));
    }

    public function errorCode(): string
    {
        return 'order_not_found';
    }
}
