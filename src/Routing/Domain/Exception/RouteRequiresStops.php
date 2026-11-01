<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class RouteRequiresStops extends DomainException
{
    public function __construct()
    {
        parent::__construct('La ruta necesita al menos un pedido.');
    }

    public function errorCode(): string
    {
        return 'route_requires_stops';
    }
}
