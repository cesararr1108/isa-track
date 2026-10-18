<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Adapter;

use Isa\Companies\Domain\CompanyRepository;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shipping\Application\Port\CompanyBrandingProvider;

final class CompaniesBrandingProvider implements CompanyBrandingProvider
{
    public function __construct(private readonly CompanyRepository $companies) {}

    public function forCompany(CompanyId $companyId): ?array
    {
        $company = $this->companies->find($companyId);

        return $company === null ? null : [
            'name' => $company->name(),
            'logo_url' => $company->branding()->logoUrl,
            'primary_color' => $company->branding()->primaryColor,
            'secondary_color' => $company->branding()->secondaryColor,
        ];
    }
}
