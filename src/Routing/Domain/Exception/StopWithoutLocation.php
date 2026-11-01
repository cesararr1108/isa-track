<?php

declare(strict_types=1);

namespace Isa\Routing\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class StopWithoutLocation extends DomainException
{
    public function __construct(string $order)
    {
        parent::__construct(sprintf('El pedido %s no tiene coordenadas; ubícalo en el mapa antes de agregarlo a una ruta.', $order));
    }

    public function errorCode(): string
    {
        return 'stop_without_location';
    }
}
