<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\Port;

use Isa\Shared\Domain\ValueObject\CompanyId;

interface CompanyBrandingProvider
{
    /** @return array{name: string, logo_url: ?string, primary_color: string, secondary_color: string}|null */
    public function forCompany(CompanyId $companyId): ?array;
}
