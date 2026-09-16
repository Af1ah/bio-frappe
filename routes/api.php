<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/ebio/webhook/{token}', [\App\Http\Controllers\EbioWebhookController::class, 'handle']);

Route::middleware(['auth:sanctum', 'abilities:device-gateway:write'])
    ->prefix('internal/v1')
    ->group(function (): void {
        Route::post('/device-events', [\App\Http\Controllers\Internal\GatewayDeviceEventController::class, 'store']);
    });
