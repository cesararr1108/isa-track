<?php

declare(strict_types=1);

namespace Isa\Companies\Application\CreateCompany;

use Isa\Companies\Domain\Branding;
use Isa\Companies\Domain\Company;
use Isa\Companies\Domain\CompanyRepository;
use Isa\Shared\Domain\ValueObject\CompanyId;

final class CreateCompanyHandler
{
    /** @param array{primary_color: string, secondary_color: string} $defaultBranding */
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly array $defaultBranding,
    ) {}

    public function __invoke(CreateCompanyCommand $command): CompanyId
    {
        $company = Company::register(
            CompanyId::generate(),
            $command->name,
            $command->taxId,
            new Branding(
                $command->logoUrl,
                $command->primaryColor ?? $this->defaultBranding['primary_color'],
                $command->secondaryColor ?? $this->defaultBranding['secondary_color'],
            ),
        );

        $this->companies->save($company);

        return $company->id();
    }
}
