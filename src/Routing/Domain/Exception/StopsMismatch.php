<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class StopsMismatch extends DomainException
{
    public function __construct()
    {
        parent::__construct('El nuevo orden debe tener exactamente los mismos pedidos de la ruta.');
    }

    public function errorCode(): string
    {
        return 'route_stops_mismatch';
    }
}
