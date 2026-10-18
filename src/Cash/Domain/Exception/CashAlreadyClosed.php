<?php

declare(strict_types=1);

namespace Isa\Cash\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class CashAlreadyClosed extends DomainException
{
    public function __construct(string $date)
    {
        parent::__construct(sprintf('La caja de este conductor para el %s ya está cerrada.', $date));
    }

    public function errorCode(): string
    {
        return 'cash_already_closed';
    }
}
