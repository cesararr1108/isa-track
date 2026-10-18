<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Isa\Shared\Application\EventBus;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Application\TransactionManager;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Infrastructure\Auth\PermissionChecker;
use Isa\Shared\Infrastructure\Laravel\LaravelEventBus;
use Isa\Shared\Infrastructure\Laravel\LaravelTransactionManager;
use Isa\Shared\Infrastructure\Laravel\SystemClock;
use Isa\Shared\Infrastructure\Tenancy\RequestTenantContext;

/**
 * "Cableado" del kernel compartido: aquí se decide qué implementación
 * concreta recibe cada interfaz (puerto) cuando Laravel la inyecta.
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EventBus::class, LaravelEventBus::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->singleton(Clock::class, fn () => new SystemClock(config('app.timezone')));

        $this->app->scoped(RequestTenantContext::class);
        $this->app->alias(RequestTenantContext::class, TenantContext::class);

        $this->app->singleton(PermissionChecker::class, fn () => new PermissionChecker(config('isa.roles')));
    }
}
