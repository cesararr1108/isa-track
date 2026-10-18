<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class CashRegisterAlreadyClosed extends DomainException
{
    public function __construct(string $date)
    {
        parent::__construct(sprintf('La caja del conductor para el %s ya fue cerrada; no se pueden registrar más recaudos ese día.', $date));
    }

    public function errorCode(): string
    {
        return 'cash_register_already_closed';
    }
}
