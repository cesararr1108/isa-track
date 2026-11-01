<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class CarrierNotAvailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('El transportador no existe o está inactivo.');
    }

    public function errorCode(): string
    {
        return 'route_carrier_not_available';
    }
}
