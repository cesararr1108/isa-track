<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Infrastructure\Auth\PermissionChecker;
use Isa\Shared\Infrastructure\Auth\Role;

/**
 * Usuarios de la empresa y sus permisos. Cada usuario tiene un rol (con sus
 * permisos de config/isa.php) y se le pueden dar permisos extra o quitar
 * algunos del rol. Solo se gestionan usuarios de la empresa actual.
 */
final class UserController
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly UserPresenter $presenter,
        private readonly PermissionChecker $permissions,
    ) {}

    /** GET /api/permissions: catálogo de permisos y lo que trae cada rol. */
    public function catalog(): JsonResponse
    {
        $catalog = config('isa.permissions');

        return new JsonResponse(['data' => [
            'permissions' => collect($catalog)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
            'roles' => collect(Role::cases())
                ->reject(fn (Role $r) => $r === Role::SuperAdmin)
                ->map(fn (Role $r) => [
                    'key' => $r->value,
                    'label' => config("isa.role_labels.{$r->value}", $r->value),
                    'permissions' => array_values(array_filter(array_keys($catalog), fn ($p) => $this->permissions->roleAllows($r, $p))),
                ])->values(),
        ]]);
    }

    public function index(): JsonResponse
    {
        $users = User::query()
            ->where('company_id', $this->tenant->companyId()->value())
            ->orderBy('name')
            ->get();

        return new JsonResponse(['data' => $users->map(fn (User $u) => $this->presenter->toArray($u))->values()]);
    }

    public function store(Request $request, UserManagement $users): JsonResponse
    {
        $data = $request->validate($users->rules(null), [], $users->attributes());

        return new JsonResponse(['data' => $this->presenter->toArray($users->create($data))], 201);
    }

    public function update(Request $request, string $user, UserManagement $users): JsonResponse
    {
        $model = $users->find($user);
        $data = $request->validate($users->rules($model), [], $users->attributes());

        return new JsonResponse(['data' => $this->presenter->toArray($users->update($model, $data, (string) $request->user()->id))]);
    }
}
