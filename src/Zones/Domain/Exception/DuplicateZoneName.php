<?php

declare(strict_types=1);

namespace Isa\Zones\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class DuplicateZoneName extends DomainException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('Ya existe una zona llamada "%s".', $name));
    }

    public function errorCode(): string
    {
        return 'duplicate_zone_name';
    }
}
