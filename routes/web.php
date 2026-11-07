<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DriverAppController;
use App\Http\Controllers\Web\LiveMapController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\UserPageController;
use Illuminate\Support\Facades\Route;
use Isa\Cash\Infrastructure\Http\Web\CashPageController;
use Isa\Fleet\Infrastructure\Http\Web\CarrierPageController;
use Isa\Reports\Infrastructure\Http\Web\ReportPageController;
use Isa\Routing\Infrastructure\Http\Web\RoutePageController;
use Isa\Shipping\Infrastructure\Http\PublicTrackingController;
use Isa\Shipping\Infrastructure\Http\Web\OrderPageController;
use Isa\Zones\Infrastructure\Http\Web\ZonePageController;

/*
| Panel web (Inertia + Vue). Sesión de servidor con cookie httpOnly y CSRF.
| Cada ruta exige su permiso en el servidor; ocultar un botón no es seguridad.
*/
Route::middleware('security.headers:csp')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
    });

    Route::middleware(['auth', 'company.user', 'tenant'])->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('panel.dashboard');

        Route::get('/pedidos', [OrderPageController::class, 'index'])->middleware('permission:orders.view')->name('panel.orders.index');
        Route::post('/pedidos', [OrderPageController::class, 'store'])->middleware('permission:orders.create')->name('panel.orders.store');
        Route::post('/pedidos/{order}/asignar', [OrderPageController::class, 'assign'])->whereUuid('order')->middleware('permission:orders.assign')->name('panel.orders.assign');
        Route::post('/pedidos/{order}/estado', [OrderPageController::class, 'changeStatus'])->whereUuid('order')->middleware('permission:orders.change_status')->name('panel.orders.status');
        Route::get('/pedidos/{order}/evidencia/{kind}', [OrderPageController::class, 'evidence'])->whereUuid('order')->whereIn('kind', ['photo', 'signature'])->middleware('permission:orders.view')->name('panel.orders.evidence');

        Route::get('/rutas', [RoutePageController::class, 'index'])->middleware('permission:routes.view')->name('panel.routes.index');
        Route::post('/rutas', [RoutePageController::class, 'store'])->middleware('permission:routes.manage')->name('panel.routes.store');
        Route::put('/rutas/{route}/paradas', [RoutePageController::class, 'reorder'])->whereUuid('route')->middleware('permission:routes.manage')->name('panel.routes.reorder');
        Route::post('/rutas/{route}/optimizar', [RoutePageController::class, 'optimize'])->whereUuid('route')->middleware('permission:routes.manage')->name('panel.routes.optimize');
        Route::post('/rutas/{route}/estado', [RoutePageController::class, 'changeStatus'])->whereUuid('route')->middleware('permission:routes.drive')->name('panel.routes.status');

        Route::get('/mapa', LiveMapController::class)->middleware('permission:orders.view')->name('panel.live');

        Route::get('/caja', [CashPageController::class, 'index'])->middleware('permission:cash.view')->name('panel.cash.index');
        Route::post('/caja/cierres', [CashPageController::class, 'close'])->middleware('permission:cash.close')->name('panel.cash.close');

        Route::get('/reportes', [ReportPageController::class, 'index'])->middleware('permission:reports.view')->name('panel.reports.index');
        Route::get('/reportes/pedidos.csv', [ReportPageController::class, 'csv'])->middleware(['permission:reports.view', 'throttle:20,1'])->name('panel.reports.csv');

        Route::get('/usuarios', [UserPageController::class, 'index'])->middleware('permission:users.manage')->name('panel.users.index');
        Route::post('/usuarios', [UserPageController::class, 'store'])->middleware('permission:users.manage')->name('panel.users.store');
        Route::put('/usuarios/{user}', [UserPageController::class, 'update'])->whereUuid('user')->middleware('permission:users.manage')->name('panel.users.update');

        Route::get('/transportadores', [CarrierPageController::class, 'index'])->middleware('permission:carriers.view')->name('panel.carriers.index');
        Route::post('/transportadores', [CarrierPageController::class, 'store'])->middleware('permission:carriers.create')->name('panel.carriers.store');
        Route::match(['put', 'post'], '/transportadores/{carrier}', [CarrierPageController::class, 'update'])->whereUuid('carrier')->middleware('permission:carriers.update')->name('panel.carriers.update');
        Route::post('/transportadores/{carrier}/estado', [CarrierPageController::class, 'setActive'])->whereUuid('carrier')->middleware('permission:carriers.update')->name('panel.carriers.active');
        Route::get('/transportadores/{carrier}/foto', [CarrierPageController::class, 'photo'])->whereUuid('carrier')->middleware('permission:carriers.view')->name('panel.carriers.photo');

        // App del mensajero (PWA). Mismos permisos que la API del conductor.
        Route::middleware('permission:routes.drive')->prefix('mensajero')->name('driver.')->group(function () {
            Route::get('/', [DriverAppController::class, 'home'])->name('home');
            Route::get('/pedidos/{order}', [DriverAppController::class, 'order'])->whereUuid('order')->middleware('permission:orders.view')->name('order');
            Route::post('/pedidos/{order}/estado', [DriverAppController::class, 'changeStatus'])->whereUuid('order')->middleware('permission:orders.change_status')->name('order.status');
            Route::post('/pedidos/{order}/entregar', [DriverAppController::class, 'deliver'])->whereUuid('order')->middleware(['permission:orders.change_status', 'throttle:30,1'])->name('order.deliver');
            Route::post('/ubicacion', [DriverAppController::class, 'location'])->middleware('throttle:30,1')->name('location');
        });

        Route::get('/zonas', [ZonePageController::class, 'index'])->middleware('permission:zones.view')->name('panel.zones.index');
        Route::get('/zonas/cotizar', [ZonePageController::class, 'quote'])->middleware('permission:zones.view')->name('panel.zones.quote');
        Route::post('/zonas', [ZonePageController::class, 'store'])->middleware('permission:zones.manage')->name('panel.zones.store');
        Route::put('/zonas/{zone}', [ZonePageController::class, 'update'])->whereUuid('zone')->middleware('permission:zones.manage')->name('panel.zones.update');
        Route::delete('/zonas/{zone}', [ZonePageController::class, 'destroy'])->whereUuid('zone')->middleware('permission:zones.manage')->name('panel.zones.destroy');
    });
});

// Página pública de rastreo (el link que recibe el cliente por WhatsApp).
Route::get('/rastreo/{code}', [PublicTrackingController::class, 'page'])->middleware(['security.headers:csp', 'throttle:60,1'])->name('tracking.show');

// Prototipo navegable (datos de ejemplo). Se retirará cuando el panel tenga todas las secciones.
Route::get('/prototipo', fn () => response(
    "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1, viewport-fit=cover\">\n"
    .'<style>[hidden]{display:none!important}body{margin:0}img{max-width:100%}</style>'."\n</head>\n<body>\n"
    .file_get_contents(base_path('docs/prototipo/index.html'))
    ."\n</body>\n</html>\n",
)->header('Content-Type', 'text/html; charset=utf-8'));
