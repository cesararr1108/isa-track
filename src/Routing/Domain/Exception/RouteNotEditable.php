<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class RouteNotEditable extends DomainException
{
    public function __construct()
    {
        parent::__construct('Solo se pueden modificar las rutas planeadas.');
    }

    public function errorCode(): string
    {
        return 'route_not_editable';
    }
}
