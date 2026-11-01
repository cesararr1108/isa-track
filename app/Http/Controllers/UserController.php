<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);

        $user = User::query()->create([
            'company_id' => $this->tenant->companyId()->value(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'carrier_id' => $data['carrier_id'] ?? null,
            'permissions' => $data['extra_permissions'] ?? [],
            'revoked_permissions' => $data['revoked_permissions'] ?? [],
            'active' => $data['active'] ?? true,
        ]);

        return new JsonResponse(['data' => $this->presenter->toArray($user->refresh())], 201);
    }

    public function update(Request $request, string $user): JsonResponse
    {
        $model = $this->find($user);
        $data = $this->validated($request, $model);

        if ($model->id === $request->user()->id && (($data['active'] ?? true) === false)) {
            abort(422, 'No puedes desactivar tu propio usuario.');
        }

        $model->fill(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : null,
            'role' => $data['role'] ?? null,
        ], fn ($v) => $v !== null));
        if (! empty($data['password'])) {
            $model->password = $data['password'];
        }
        foreach (['carrier_id', 'active'] as $field) {
            if (array_key_exists($field, $data)) {
                $model->{$field} = $data[$field];
            }
        }
        if (array_key_exists('extra_permissions', $data)) {
            $model->permissions = array_values(array_unique($data['extra_permissions'] ?? []));
        }
        if (array_key_exists('revoked_permissions', $data)) {
            $model->revoked_permissions = array_values(array_unique($data['revoked_permissions'] ?? []));
        }
        if ($model->role !== Role::Driver) {
            $model->carrier_id = null;
        }
        $model->save();

        // Si le quitan el acceso, se cierran sus sesiones.
        if ($model->active === false) {
            $model->tokens()->delete();
        }

        return new JsonResponse(['data' => $this->presenter->toArray($model->refresh())]);
    }

    private function find(string $id): User
    {
        return User::query()
            ->where('company_id', $this->tenant->companyId()->value())
            ->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $existing): array
    {
        $creating = $existing === null;
        $catalog = array_keys(config('isa.permissions'));
        $assignableRoles = array_map(fn (Role $r) => $r->value, array_filter(Role::cases(), fn (Role $r) => $r !== Role::SuperAdmin));
        $companyId = $this->tenant->companyId()->value();

        $data = $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:150', Rule::unique('users', 'email')->ignore($existing?->id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => [$creating ? 'required' : 'sometimes', Rule::in($assignableRoles)],
            'carrier_id' => ['nullable', 'uuid', Rule::exists('carriers', 'id')->where('company_id', $companyId)],
            'active' => ['sometimes', 'boolean'],
            'extra_permissions' => ['sometimes', 'nullable', 'array'],
            'extra_permissions.*' => ['string', Rule::in($catalog)],
            'revoked_permissions' => ['sometimes', 'nullable', 'array'],
            'revoked_permissions.*' => ['string', Rule::in($catalog)],
        ], [
            'extra_permissions.*.in' => 'El permiso :input no existe.',
            'revoked_permissions.*.in' => 'El permiso :input no existe.',
        ]);

        $role = $data['role'] ?? $existing?->role->value;
        $carrier = array_key_exists('carrier_id', $data) ? $data['carrier_id'] : $existing?->carrier_id;
        if ($role === Role::Driver->value && $carrier === null) {
            abort(422, 'Un usuario mensajero debe estar vinculado a un transportador (carrier_id).');
        }
        if ($carrier !== null && User::query()
            ->where('carrier_id', $carrier)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists()) {
            abort(422, 'Ese transportador ya tiene un usuario.');
        }

        return $data;
    }
}
