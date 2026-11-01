<?php

declare(strict_types=1);

namespace Isa\Zones\Domain\Exception;

use Isa\Shared\Domain\NotFoundException;

final class ZoneNotFound extends NotFoundException
{
    public function __construct(string $zoneId)
    {
        parent::__construct(sprintf('No existe la zona <%s>.', $zoneId));
    }

    public function errorCode(): string
    {
        return 'zone_not_found';
    }
}
