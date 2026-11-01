<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class DuplicateStop extends DomainException
{
    public function __construct(string $orderId)
    {
        parent::__construct(sprintf('El pedido %s está repetido en la ruta.', $orderId));
    }

    public function errorCode(): string
    {
        return 'duplicate_route_stop';
    }
}
