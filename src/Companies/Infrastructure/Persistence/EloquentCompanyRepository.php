<?php

declare(strict_types=1);

namespace Isa\Companies\Infrastructure\Persistence;

use Isa\Companies\Domain\Branding;
use Isa\Companies\Domain\Company;
use Isa\Companies\Domain\CompanyRepository;
use Isa\Shared\Domain\ValueObject\CompanyId;

final class EloquentCompanyRepository implements CompanyRepository
{
    public function save(Company $company): void
    {
        CompanyModel::query()->updateOrCreate(
            ['id' => $company->id()->value()],
            [
                'name' => $company->name(),
                'tax_id' => $company->taxId(),
                'logo_url' => $company->branding()->logoUrl,
                'primary_color' => $company->branding()->primaryColor,
                'secondary_color' => $company->branding()->secondaryColor,
            ],
        );
    }

    public function find(CompanyId $id): ?Company
    {
        $row = CompanyModel::query()->find($id->value());

        return $row === null ? null : Company::restore(
            CompanyId::fromString($row->id),
            $row->name,
            $row->tax_id,
            new Branding($row->logo_url, $row->primary_color, $row->secondary_color),
        );
    }
}
