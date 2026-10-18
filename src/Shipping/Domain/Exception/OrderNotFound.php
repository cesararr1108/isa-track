<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\NotFoundException;

final class OrderNotFound extends NotFoundException
{
    public function __construct(string $reference)
    {
        parent::__construct(sprintf('No existe el pedido <%s>.', $reference));
    }

    public function errorCode(): string
    {
        return 'order_not_found';
    }
}
