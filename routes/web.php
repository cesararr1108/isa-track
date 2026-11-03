<?php

use Illuminate\Support\Facades\Route;
use Isa\Shipping\Infrastructure\Http\PublicTrackingController;

Route::get('/', fn () => response()->json(['app' => config('app.name'), 'status' => 'ok']));

// Página pública de rastreo (el link que recibe el cliente por WhatsApp).
Route::get('/rastreo/{code}', [PublicTrackingController::class, 'page'])->name('tracking.show');

// Prototipo navegable con dirección web: así OpenStreetMap acepta las teselas del mapa.
Route::get('/prototipo', fn () => response(
    "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1, viewport-fit=cover\">\n"
    .'<style>[hidden]{display:none!important}body{margin:0}img{max-width:100%}</style>'."\n</head>\n<body>\n"
    .file_get_contents(base_path('docs/prototipo/index.html'))
    ."\n</body>\n</html>\n",
)->header('Content-Type', 'text/html; charset=utf-8'));
