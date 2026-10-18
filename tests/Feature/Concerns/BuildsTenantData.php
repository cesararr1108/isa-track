<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Models\User;
use Isa\Companies\Application\CreateCompany\CreateCompanyCommand;
use Isa\Companies\Application\CreateCompany\CreateCompanyHandler;
use Isa\Fleet\Infrastructure\Persistence\CarrierModel;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Infrastructure\Auth\Role;

/** Ayudantes para armar empresas, transportadores, usuarios y pedidos en las pruebas. */
trait BuildsTenantData
{
    private function company(string $name): CompanyId
    {
        return app(CreateCompanyHandler::class)(new CreateCompanyCommand($name));
    }

    private function carrier(CompanyId $company): string
    {
        return CarrierModel::query()->create([
            'company_id' => $company->value(), 'name' => 'Juan Pérez', 'type' => 'own_driver', 'active' => true,
        ])->id;
    }

    private function user(CompanyId $company, Role $role, ?string $carrierId = null): User
    {
        return User::factory()->create(['company_id' => $company->value(), 'role' => $role, 'carrier_id' => $carrierId]);
    }

    /** @return array<string, mixed> */
    private function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'external_reference' => 'WEB-1001',
            'recipient' => ['name' => 'Laura Gómez', 'phone' => '+57 300 123 4567'],
            'address' => ['line' => 'Cra 43A # 1-50', 'neighborhood' => 'El Poblado', 'reference' => 'Portería'],
            'packages' => [['description' => 'Caja', 'weight_kg' => 2], ['description' => 'Sobre']],
        ], $overrides);
    }
}
