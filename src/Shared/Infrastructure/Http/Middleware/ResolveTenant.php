<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Infrastructure\Auth\Role;
use Isa\Shared\Infrastructure\Tenancy\RequestTenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa de la petición:
 * - Usuarios normales: la empresa a la que pertenecen.
 * - Superadmin: la que envíe en el header X-Company-Id (opcional).
 */
final class ResolveTenant
{
    public function __construct(private readonly RequestTenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $companyId = $user->role === Role::SuperAdmin
                ? $request->header('X-Company-Id')
                : $user->company_id;

            if (is_string($companyId) && $companyId !== '') {
                $this->tenant->set(CompanyId::fromString($companyId));
            }
        }

        return $next($request);
    }
}
