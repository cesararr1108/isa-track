<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Isa\Shared\Domain\DomainException;
use Isa\Shared\Infrastructure\Http\DomainExceptionRenderer;
use Isa\Shared\Infrastructure\Http\Middleware\EnsurePermission;
use Isa\Shared\Infrastructure\Http\Middleware\ResolveTenant;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias para usar en las rutas: ->middleware(['tenant', 'permission:orders.create'])
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Reglas de negocio violadas => 422 / 404 con un código de error estable.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(DomainExceptionRenderer::class)->render($e);
            }
        });
        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(DomainExceptionRenderer::class)->renderInvalidArgument($e);
            }
        });
    })->create();
