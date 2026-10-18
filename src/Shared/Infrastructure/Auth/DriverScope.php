<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Auth;

use Illuminate\Http\Request;

/**
 * Si quien hace la petición es un conductor, devuelve su transportador:
 * los controladores lo usan para limitarlo a sus propios pedidos y su caja.
 */
final class DriverScope
{
    /** Un conductor sin transportador vinculado no puede ver ni mover nada. */
    private const NO_CARRIER = '00000000-0000-0000-0000-000000000000';

    public static function carrierId(Request $request): ?string
    {
        $user = $request->user();

        return $user->role === Role::Driver ? ($user->carrier_id ?? self::NO_CARRIER) : null;
    }
}
