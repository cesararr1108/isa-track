<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Isa\Shared\Infrastructure\Auth\PermissionChecker;
use Isa\Shared\Infrastructure\Auth\Role;
use PHPUnit\Framework\TestCase;

final class PermissionCheckerTest extends TestCase
{
    public function test_supports_exact_and_wildcard_permissions(): void
    {
        $checker = new PermissionChecker([
            'superadmin' => ['*'],
            'dispatcher' => ['orders.*'],
            'driver' => ['orders.change_status'],
        ]);

        self::assertTrue($checker->allows(Role::SuperAdmin, 'companies.create'));
        self::assertTrue($checker->allows(Role::Dispatcher, 'orders.assign'));
        self::assertFalse($checker->allows(Role::Dispatcher, 'carriers.create'));
        self::assertTrue($checker->allows(Role::Driver, 'orders.change_status'));
        self::assertFalse($checker->allows(Role::Driver, 'orders.create'));
        self::assertFalse($checker->allows(Role::Viewer, 'orders.view'), 'Rol sin entrada en config = sin permisos');
    }
}
