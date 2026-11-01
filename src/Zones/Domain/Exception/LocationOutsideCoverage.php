<?php

declare(strict_types=1);

namespace Isa\Zones\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class LocationOutsideCoverage extends DomainException
{
    public function __construct()
    {
        parent::__construct('La dirección está fuera de las zonas de cobertura.');
    }

    public function errorCode(): string
    {
        return 'location_outside_coverage';
    }
}
