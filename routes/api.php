<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Isa\Cash\Infrastructure\Http\CashController;
use Isa\Companies\Infrastructure\Http\CompanyController;
use Isa\Fleet\Infrastructure\Http\CarrierController;
use Isa\Shipping\Infrastructure\Http\OrderController;
use Isa\Shipping\Infrastructure\Http\PublicTrackingController;

/*
| Todas estas rutas llevan el prefijo /api (lo agrega Laravel).
*/

// Públicas
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/public/tracking/{code}', [PublicTrackingController::class, 'json'])->middleware('throttle:60,1');

// Privadas: token Sanctum + empresa resuelta
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/companies', [CompanyController::class, 'store'])->middleware('permission:companies.create');

    Route::get('/carriers', [CarrierController::class, 'index'])->middleware('permission:carriers.view');
    Route::post('/carriers', [CarrierController::class, 'store'])->middleware('permission:carriers.create');

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
