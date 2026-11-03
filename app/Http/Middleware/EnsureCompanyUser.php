<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El panel trabaja dentro de una empresa: el superadministrador usa la API con X-Company-Id. */
final class EnsureCompanyUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->company_id === null) {
            abort(403, 'Tu usuario no pertenece a una empresa. El superadministrador usa la API con el encabezado X-Company-Id.');
        }

        return $next($request);
    }
}
