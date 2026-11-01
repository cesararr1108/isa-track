<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Isa\Shared\Infrastructure\Auth\Role;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuario de la plataforma. Pertenece a una empresa (salvo el superadmin)
 * y, si es conductor, está vinculado a un transportador (carrier_id), de donde
 * salen su foto, cédula y vehículo. Además de los permisos de su rol puede
 * tener permisos extra (permissions) o quitados (revoked_permissions).
 */
#[Fillable(['name', 'email', 'password', 'role', 'company_id', 'carrier_id', 'permissions', 'revoked_permissions', 'phone', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'permissions' => 'array',
            'revoked_permissions' => 'array',
            'active' => 'boolean',
        ];
    }
}
