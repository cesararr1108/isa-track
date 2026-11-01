<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class RouteExceedsCapacity extends DomainException
{
    public function __construct(string $detail)
    {
        parent::__construct(sprintf('La ruta supera la capacidad del vehículo: %s.', $detail));
    }

    public function errorCode(): string
    {
        return 'route_exceeds_carrier_capacity';
    }
}
