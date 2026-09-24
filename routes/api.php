<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OutboundManualController;
use App\Http\Controllers\Api\V1\StockController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(['stock.api.access', 'throttle:stock-api'])->group(function () {
        Route::get('/health', HealthController::class);
        Route::get('/stocks', [StockController::class, 'index']);
    });

    Route::post('/outbound/manuals', [OutboundManualController::class, 'store'])
        ->middleware(['outbound.manual.api.access', 'throttle:outbound-manual-api']);
});
