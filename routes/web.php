<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LoginController;
use Illuminate\Support\Facades\Route;
use Isa\Fleet\Infrastructure\Http\Web\CarrierPageController;
use Isa\Shipping\Infrastructure\Http\PublicTrackingController;
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

        Route::get('/transportadores', [CarrierPageController::class, 'index'])->middleware('permission:carriers.view')->name('panel.carriers.index');
        Route::post('/transportadores', [CarrierPageController::class, 'store'])->middleware('permission:carriers.create')->name('panel.carriers.store');
        Route::match(['put', 'post'], '/transportadores/{carrier}', [CarrierPageController::class, 'update'])->whereUuid('carrier')->middleware('permission:carriers.update')->name('panel.carriers.update');
        Route::post('/transportadores/{carrier}/estado', [CarrierPageController::class, 'setActive'])->whereUuid('carrier')->middleware('permission:carriers.update')->name('panel.carriers.active');
        Route::get('/transportadores/{carrier}/foto', [CarrierPageController::class, 'photo'])->whereUuid('carrier')->middleware('permission:carriers.view')->name('panel.carriers.photo');

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
