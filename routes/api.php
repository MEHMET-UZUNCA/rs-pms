<?php

use App\Http\Controllers\Api\V1\WebController;
use App\Http\Middleware\AuthenticateApiClient;
use Illuminate\Support\Facades\Route;

// Web API v1 — otelin web sitesi bilgileri buradan okur (docs/11-web-api.md). Anahtar: php artisan pms:api-client "Web sitesi"
Route::prefix('v1')->middleware([AuthenticateApiClient::class, 'throttle:120,1'])->group(function () {
    Route::get('/room-types', [WebController::class, 'roomTypes']);
    Route::get('/rate-codes', [WebController::class, 'rateCodes']);
    Route::get('/availability', [WebController::class, 'availability']);
});
