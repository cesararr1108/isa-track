<?php

use Illuminate\Support\Facades\Route;
use Isa\Shipping\Infrastructure\Http\PublicTrackingController;

Route::get('/', fn () => response()->json(['app' => config('app.name'), 'status' => 'ok']));

// Página pública de rastreo (el link que recibe el cliente por WhatsApp).
Route::get('/rastreo/{code}', [PublicTrackingController::class, 'page'])->name('tracking.show');
