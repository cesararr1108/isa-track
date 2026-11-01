<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class InvalidRouteStatusChange extends DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(sprintf('No se puede pasar la ruta de "%s" a "%s".', $from, $to));
    }

    public function errorCode(): string
    {
        return 'invalid_route_status_change';
    }
}
