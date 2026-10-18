<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class DeliveryRequiresProof extends DomainException
{
    public function __construct()
    {
        parent::__construct('Para marcar un pedido como entregado hay que registrar la entrega con su prueba.');
    }

    public function errorCode(): string
    {
        return 'delivery_requires_proof';
    }
}
