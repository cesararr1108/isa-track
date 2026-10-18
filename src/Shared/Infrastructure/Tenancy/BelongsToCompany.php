<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Isa\Shared\Application\TenantContext;

/**
 * Trait para modelos Eloquent con columna company_id.
 *
 * - Agrega un "global scope": toda consulta se filtra por la empresa actual.
 * - Al crear un registro, completa company_id si no viene.
 *
 * Es una segunda barrera: los repositorios también filtran explícitamente.
 * Para consultas entre empresas (p. ej. rastreo público) se usa
 * Model::withoutGlobalScope('company').
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            $tenant = app(TenantContext::class);
            if ($tenant->hasCompany()) {
                $query->where($query->qualifyColumn('company_id'), $tenant->companyId()->value());
            }
        });

        static::creating(function ($model): void {
            $tenant = app(TenantContext::class);
            if (empty($model->company_id) && $tenant->hasCompany()) {
                $model->company_id = $tenant->companyId()->value();
            }
        });
    }
}
