<?php

declare(strict_types=1);

namespace Isa\Companies\Domain;

use Isa\Shared\Domain\ValueObject\CompanyId;

interface CompanyRepository
{
    public function save(Company $company): void;

    public function find(CompanyId $id): ?Company;
}
