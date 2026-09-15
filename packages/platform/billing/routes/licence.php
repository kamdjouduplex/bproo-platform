<?php

use Bproo\Platform\Billing\Http\Controllers\DesktopLicenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Desktop licence API (Control Center only)
|--------------------------------------------------------------------------
|
| Additive to SaaS billing. Auth = activation_code (activate) or signed
| token (heartbeat). Do not gate SaaS middleware with these endpoints.
|
*/

Route::prefix('licence')->middleware('throttle:30,1')->group(function () {
    Route::post('/activate', [DesktopLicenceController::class, 'activate'])
        ->name('licence.activate');
    Route::post('/heartbeat', [DesktopLicenceController::class, 'heartbeat'])
        ->name('licence.heartbeat');
});
