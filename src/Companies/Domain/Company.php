<?php

declare(strict_types=1);

namespace Isa\Companies\Domain;

use InvalidArgumentException;
use Isa\Shared\Domain\AggregateRoot;
use Isa\Shared\Domain\ValueObject\CompanyId;

final class Company extends AggregateRoot
{
    private function __construct(
        private readonly CompanyId $id,
        private string $name,
        private readonly ?string $taxId,
        private Branding $branding,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('El nombre de la empresa es obligatorio.');
        }
    }

    public static function register(CompanyId $id, string $name, ?string $taxId, Branding $branding): self
    {
        return new self($id, $name, $taxId, $branding);
    }

    /** Reconstruye desde persistencia, sin disparar eventos. */
    public static function restore(CompanyId $id, string $name, ?string $taxId, Branding $branding): self
    {
        return new self($id, $name, $taxId, $branding);
    }

    public function rebrand(Branding $branding): void
    {
        $this->branding = $branding;
    }

    public function id(): CompanyId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function taxId(): ?string
    {
        return $this->taxId;
    }

    public function branding(): Branding
    {
        return $this->branding;
    }
}
