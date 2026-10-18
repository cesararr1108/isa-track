<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Isa\Shared\Infrastructure\Auth\PermissionChecker;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas: ->middleware('permission:orders.create')
 */
final class EnsurePermission
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->permissions->allows($user->role, $permission)) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $next($request);
    }
}
