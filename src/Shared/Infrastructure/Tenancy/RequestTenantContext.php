<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Tenancy;

use Isa\Shared\Application\TenantContext;
use Isa\Shared\Application\TenantNotResolved;
use Isa\Shared\Domain\ValueObject\CompanyId;

/**
 * Guarda la empresa de la petición en curso. El middleware ResolveTenant
 * la fija a partir del usuario autenticado. Se registra como "scoped",
 * es decir, una instancia nueva por petición.
 */
final class RequestTenantContext implements TenantContext
{
    private ?CompanyId $companyId = null;

    public function set(CompanyId $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function hasCompany(): bool
    {
        return $this->companyId !== null;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId ?? throw new TenantNotResolved;
    }
}
