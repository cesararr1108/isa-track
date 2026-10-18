<?php

declare(strict_types=1);

namespace Isa\Shared\Application;

use Isa\Shared\Domain\ValueObject\CompanyId;

/**
 * Empresa (tenant) sobre la que se ejecuta la petición actual.
 * Todo caso de uso privado trabaja dentro de una sola empresa.
 */
interface TenantContext
{
    public function hasCompany(): bool;

    /** @throws TenantNotResolved */
    public function companyId(): CompanyId;
}
