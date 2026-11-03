<?php

use App\Http\Middleware\EnsureCompanyUser;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Isa\Shared\Domain\DomainException;
use Isa\Shared\Domain\NotFoundException;
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
            'security.headers' => SecurityHeaders::class,
            'company.user' => EnsureCompanyUser::class,
        ]);
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->api(append: [SecurityHeaders::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel.dashboard'));
        // Detrás de Nginx/Cloudflare: confiar en los encabezados X-Forwarded-* del proxy local.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $api = fn (Request $request) => $request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'));

        // Reglas de negocio violadas => 422 / 404 con un código de error estable (API)
        // o de vuelta al formulario con el mensaje (panel).
        $exceptions->render(function (DomainException $e, Request $request) use ($api) {
            if ($api($request)) {
                return app(DomainExceptionRenderer::class)->render($e);
            }
            if ($e instanceof NotFoundException) {
                abort(404, $e->getMessage());
            }

            return back()->withErrors(['general' => $e->getMessage()])->withInput($request->except(['password', 'photo']));
        });
        $exceptions->render(function (InvalidArgumentException $e, Request $request) use ($api) {
            if ($api($request)) {
                return app(DomainExceptionRenderer::class)->renderInvalidArgument($e);
            }

            return back()->withErrors(['general' => $e->getMessage()])->withInput($request->except(['password', 'photo']));
        });
    })->create();
