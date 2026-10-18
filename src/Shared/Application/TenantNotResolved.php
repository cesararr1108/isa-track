<?php

declare(strict_types=1);

namespace Isa\Shared\Application;

use Isa\Shared\Domain\DomainException;

final class TenantNotResolved extends DomainException
{
    public function __construct()
    {
        parent::__construct('No se pudo determinar la empresa de la petición.');
    }

    public function errorCode(): string
    {
        return 'tenant_not_resolved';
    }
}
