<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class CollectionAmountMismatch extends DomainException
{
    public function __construct(int $expected, int $collected)
    {
        parent::__construct(sprintf('El pedido debe recaudar $%s y se registraron $%s.', number_format($expected, 0, ',', '.'), number_format($collected, 0, ',', '.')));
    }

    public function errorCode(): string
    {
        return 'collection_amount_mismatch';
    }
}
