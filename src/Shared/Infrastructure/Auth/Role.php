<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Auth;

/** Roles del sistema. Los permisos de cada rol se configuran en config/isa.php. */
enum Role: string
{
    case SuperAdmin = 'superadmin';
    case CompanyAdmin = 'company_admin';
    case Dispatcher = 'dispatcher';
    case Driver = 'driver';
    case Viewer = 'viewer';
}
