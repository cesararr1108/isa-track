<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Auth;

/**
 * Decide si un usuario tiene un permiso.
 *
 * 1. Lo que su ROL tiene en config/isa.php (comodines "*" y "orders.*").
 * 2. Más los permisos EXTRA que se le dieron a él (users.permissions).
 * 3. Menos los permisos que se le QUITARON a él (users.revoked_permissions).
 *    Quitar gana sobre dar. Al superadmin no se le puede quitar nada.
 */
final class PermissionChecker
{
    /** @param array<string, list<string>> $permissionsByRole */
    public function __construct(private readonly array $permissionsByRole) {}

    /**
     * @param  list<string>  $granted  permisos extra del usuario
     * @param  list<string>  $revoked  permisos quitados al usuario
     */
    public function allows(Role $role, string $permission, array $granted = [], array $revoked = []): bool
    {
        if ($role !== Role::SuperAdmin && self::matchesAny($revoked, $permission)) {
            return false;
        }

        return self::matchesAny($this->permissionsByRole[$role->value] ?? [], $permission)
            || self::matchesAny($granted, $permission);
    }

    /** ¿El rol, por sí solo, incluye este permiso? */
    public function roleAllows(Role $role, string $permission): bool
    {
        return self::matchesAny($this->permissionsByRole[$role->value] ?? [], $permission);
    }

    /**
     * Permisos efectivos del usuario dentro de un catálogo.
     *
     * @param  list<string>  $catalog
     * @param  list<string>  $granted
     * @param  list<string>  $revoked
     * @return list<string>
     */
    public function effective(Role $role, array $catalog, array $granted = [], array $revoked = []): array
    {
        return array_values(array_filter($catalog, fn (string $p) => $this->allows($role, $p, $granted, $revoked)));
    }

    /** @param list<string> $patterns */
    private static function matchesAny(array $patterns, string $permission): bool
    {
        foreach ($patterns as $granted) {
            if ($granted === '*' || $granted === $permission) {
                return true;
            }
            if (str_ends_with($granted, '.*') && str_starts_with($permission, substr($granted, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
