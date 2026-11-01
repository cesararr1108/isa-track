<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\NotFoundException;

final class RouteNotFound extends NotFoundException
{
    public function __construct(string $routeId)
    {
        parent::__construct(sprintf('No existe la ruta <%s>.', $routeId));
    }

    public function errorCode(): string
    {
        return 'route_not_found';
    }
}
