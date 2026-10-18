<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Auth;

/**
 * Decide si un rol tiene un permiso según el mapa de config/isa.php.
 * Soporta comodines: "*" (todo) y "orders.*" (todo lo de pedidos).
 */
final class PermissionChecker
{
    /** @param array<string, list<string>> $permissionsByRole */
    public function __construct(private readonly array $permissionsByRole) {}

    public function allows(Role $role, string $permission): bool
    {
        foreach ($this->permissionsByRole[$role->value] ?? [] as $granted) {
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
