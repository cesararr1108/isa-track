<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Isa\Cash\Infrastructure\Http\CashController;
use Isa\Companies\Infrastructure\Http\CompanyController;
use Isa\Fleet\Infrastructure\Http\CarrierController;
use Isa\Reports\Infrastructure\Http\ReportController;
use Isa\Routing\Infrastructure\Http\RouteController;
use Isa\Shipping\Infrastructure\Http\OrderController;
use Isa\Shipping\Infrastructure\Http\PublicTrackingController;
use Isa\Zones\Infrastructure\Http\ZoneController;

/*
| Todas estas rutas llevan el prefijo /api (lo agrega Laravel).
*/

// Públicas
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/public/tracking/{code}', [PublicTrackingController::class, 'json'])->middleware('throttle:60,1');
Route::get('/public/tracking/{code}/carrier-photo', [PublicTrackingController::class, 'carrierPhoto'])->middleware('throttle:60,1');

// Privadas: token Sanctum + empresa resuelta
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/me/photo', [CarrierController::class, 'uploadMyPhoto']);

    // Usuarios y permisos
    Route::get('/permissions', [UserController::class, 'catalog'])->middleware('permission:users.manage');
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.manage');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');

    Route::post('/companies', [CompanyController::class, 'store'])->middleware('permission:companies.create');

    Route::get('/carriers', [CarrierController::class, 'index'])->middleware('permission:carriers.view');
    Route::post('/carriers', [CarrierController::class, 'store'])->middleware('permission:carriers.create');
    Route::get('/carriers/{carrier}', [CarrierController::class, 'show'])->middleware('permission:carriers.view');
    Route::put('/carriers/{carrier}', [CarrierController::class, 'update'])->middleware('permission:carriers.update');
    Route::post('/carriers/{carrier}/active', [CarrierController::class, 'setActive'])->middleware('permission:carriers.update');
    Route::post('/carriers/{carrier}/photo', [CarrierController::class, 'uploadPhoto'])->middleware('permission:carriers.update');
    Route::get('/carriers/{carrier}/photo', [CarrierController::class, 'photo'])->middleware('permission:carriers.view');

    // Zonas y tarifas de domicilio
    Route::get('/zones', [ZoneController::class, 'index'])->middleware('permission:zones.view');
    Route::get('/zones/quote', [ZoneController::class, 'quote'])->middleware('permission:zones.view');
    Route::post('/zones', [ZoneController::class, 'store'])->middleware('permission:zones.manage');
    Route::put('/zones/{zone}', [ZoneController::class, 'update'])->middleware('permission:zones.manage');
    Route::delete('/zones/{zone}', [ZoneController::class, 'destroy'])->middleware('permission:zones.manage');

    // Rutas programadas
    Route::get('/routes', [RouteController::class, 'index'])->middleware('permission:routes.view');
    Route::post('/routes', [RouteController::class, 'store'])->middleware('permission:routes.manage');
    Route::get('/routes/{route}', [RouteController::class, 'show'])->middleware('permission:routes.view');
    Route::get('/routes/{route}/geojson', [RouteController::class, 'geoJson'])->middleware('permission:routes.view');
    Route::put('/routes/{route}/stops', [RouteController::class, 'reorder'])->middleware('permission:routes.manage');
    Route::post('/routes/{route}/optimize', [RouteController::class, 'optimize'])->middleware('permission:routes.manage');
    Route::post('/routes/{route}/status', [RouteController::class, 'changeStatus'])->middleware('permission:routes.drive');

    // Reportes por rango de fechas
    Route::get('/reports/summary', [ReportController::class, 'summary'])->middleware('permission:reports.view');
    Route::get('/reports/orders.csv', [ReportController::class, 'ordersCsv'])->middleware('permission:reports.view');

    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:orders.view');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('permission:orders.create');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view');
    Route::post('/orders/{order}/assign', [OrderController::class, 'assign'])->middleware('permission:orders.assign');
    Route::post('/orders/{order}/status', [OrderController::class, 'changeStatus'])->middleware('permission:orders.change_status');
    Route::post('/orders/{order}/deliver', [OrderController::class, 'deliver'])->middleware('permission:orders.change_status');
    Route::get('/orders/{order}/evidence/{kind}', [OrderController::class, 'evidence'])
        ->whereIn('kind', ['photo', 'signature'])->middleware('permission:orders.view');

    Route::get('/cash/summary', [CashController::class, 'summary'])->middleware('permission:cash.view');
    Route::get('/cash/closings', [CashController::class, 'index'])->middleware('permission:cash.view');
    Route::post('/cash/closings', [CashController::class, 'store'])->middleware('permission:cash.close');
});
