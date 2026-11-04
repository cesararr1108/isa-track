<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Infrastructure\Audit\AuditLog;
use Isa\Shared\Infrastructure\Auth\Role;

/**
 * Alta y edición de usuarios de la empresa con sus permisos. La usan la API y el panel.
 * Reglas: nadie crea superadministradores, un mensajero va vinculado a un transportador
 * (uno por transportador), nadie se desactiva a sí mismo y cada cambio queda en la bitácora.
 */
final class UserManagement
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLog $audit,
    ) {}

    public function find(string $id): User
    {
        return User::query()->where('company_id', $this->tenant->companyId()->value())->findOrFail($id);
    }

    /** @return array<string, mixed> */
    public function rules(?User $existing): array
    {
        $creating = $existing === null;
        $catalog = array_keys(config('isa.permissions'));
        $roles = array_map(fn (Role $r) => $r->value, array_filter(Role::cases(), fn (Role $r) => $r !== Role::SuperAdmin));

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:150', Rule::unique('users', 'email')->ignore($existing?->id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'max:100', Password::min(8)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => [$creating ? 'required' : 'sometimes', Rule::in($roles)],
            'carrier_id' => ['nullable', 'uuid', Rule::exists('carriers', 'id')->where('company_id', $this->tenant->companyId()->value())],
            'active' => ['sometimes', 'boolean'],
            'extra_permissions' => ['sometimes', 'nullable', 'array'],
            'extra_permissions.*' => ['string', Rule::in($catalog)],
            'revoked_permissions' => ['sometimes', 'nullable', 'array'],
            'revoked_permissions.*' => ['string', Rule::in($catalog)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña', 'phone' => 'teléfono', 'role' => 'rol', 'carrier_id' => 'transportador'];
    }

    /** @param array<string, mixed> $data datos ya validados con rules(null) */
    public function create(array $data): User
    {
        $this->guardCarrier($data['role'], $data['carrier_id'] ?? null, null);

        $user = User::query()->create([
            'company_id' => $this->tenant->companyId()->value(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'carrier_id' => $data['role'] === Role::Driver->value ? ($data['carrier_id'] ?? null) : null,
            'permissions' => array_values(array_unique($data['extra_permissions'] ?? [])),
            'revoked_permissions' => array_values(array_unique($data['revoked_permissions'] ?? [])),
            'active' => $data['active'] ?? true,
        ]);
        $this->audit->record('user.created', 'user', $user->id, ['email' => $user->email, 'role' => $data['role']]);

        return $user->refresh();
    }

    /** @param array<string, mixed> $data datos ya validados con rules($user) */
    public function update(User $user, array $data, string $actorId): User
    {
        if ($user->id === $actorId && array_key_exists('active', $data) && ! $data['active']) {
            throw ValidationException::withMessages(['active' => 'No puedes desactivar tu propio usuario.']);
        }
        $role = $data['role'] ?? $user->role->value;
        $carrier = array_key_exists('carrier_id', $data) ? $data['carrier_id'] : $user->carrier_id;
        $this->guardCarrier($role, $carrier, $user);

        $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'phone', 'role'])));
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if (array_key_exists('active', $data)) {
            $user->active = (bool) $data['active'];
        }
        if (array_key_exists('extra_permissions', $data)) {
            $user->permissions = array_values(array_unique($data['extra_permissions'] ?? []));
        }
        if (array_key_exists('revoked_permissions', $data)) {
            $user->revoked_permissions = array_values(array_unique($data['revoked_permissions'] ?? []));
        }
        $user->carrier_id = $role === Role::Driver->value ? $carrier : null;

        $changes = array_keys($user->getDirty());
        $user->save();

        if ($changes !== []) {
            $this->audit->record('user.updated', 'user', $user->id, [
                'email' => $user->email,
                'changed' => array_values(array_map(fn ($c) => $c === 'password' ? 'contraseña' : $c, $changes)),
                'role' => $user->role->value,
                'extra_permissions' => $user->permissions ?? [],
                'revoked_permissions' => $user->revoked_permissions ?? [],
            ]);
        }

        // Sin acceso o con nueva contraseña: se cierran sus sesiones abiertas (panel y app).
        if ($user->active === false || in_array('password', $changes, true)) {
            $user->tokens()->delete();
            if ($user->id !== $actorId && Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        }

        return $user->refresh();
    }

    private function guardCarrier(string $role, ?string $carrierId, ?User $existing): void
    {
        if ($role !== Role::Driver->value) {
            return;
        }
        if ($carrierId === null) {
            throw ValidationException::withMessages(['carrier_id' => 'Un usuario mensajero debe estar vinculado a un transportador.']);
        }
        $taken = User::query()->where('carrier_id', $carrierId)->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['carrier_id' => 'Ese transportador ya tiene un usuario.']);
        }
    }
}
