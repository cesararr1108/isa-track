<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Isa\Fleet\Infrastructure\Persistence\CarrierModel;
use Isa\Shared\Infrastructure\Auth\PermissionChecker;

/** Cómo se muestra un usuario: rol, permisos efectivos y, si es mensajero, su foto. */
final class UserPresenter
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    /** @return array<string, mixed> */
    public function toArray(User $user): array
    {
        $carrier = $user->carrier_id !== null
            ? CarrierModel::query()->withoutGlobalScope('company')->find($user->carrier_id, ['id', 'name', 'photo_path', 'vehicle_plate'])
            : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'active' => $user->active !== false,
            'company_id' => $user->company_id,
            'carrier_id' => $user->carrier_id,
            'carrier' => $carrier === null ? null : [
                'id' => $carrier->id,
                'name' => $carrier->name,
                'vehicle_plate' => $carrier->vehicle_plate,
                'photo_url' => $carrier->photo_path !== null ? url("/api/carriers/{$carrier->id}/photo") : null,
            ],
            'extra_permissions' => array_values($user->permissions ?? []),
            'revoked_permissions' => array_values($user->revoked_permissions ?? []),
            'permissions' => $this->permissions->effective(
                $user->role,
                array_keys(config('isa.permissions')),
                $user->permissions ?? [],
                $user->revoked_permissions ?? [],
            ),
        ];
    }
}
