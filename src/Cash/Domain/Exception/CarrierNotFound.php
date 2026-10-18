<?php

declare(strict_types=1);

namespace Isa\Cash\Domain\Exception;

use Isa\Shared\Domain\NotFoundException;

final class CarrierNotFound extends NotFoundException
{
    public function __construct(string $carrierId)
    {
        parent::__construct(sprintf('No existe el transportador <%s>.', $carrierId));
    }

    public function errorCode(): string
    {
        return 'carrier_not_found';
    }
}
