<?php

declare(strict_types=1);

namespace Isa\Companies\Application\CreateCompany;

final class CreateCompanyCommand
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $taxId = null,
        public readonly ?string $logoUrl = null,
        public readonly ?string $primaryColor = null,
        public readonly ?string $secondaryColor = null,
    ) {}
}
