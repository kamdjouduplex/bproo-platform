<?php

use Bproo\Platform\Billing\Http\Controllers\DesktopUpdateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Desktop update API (Control Center only)
|--------------------------------------------------------------------------
|
| Additive Phase 2 channel. Auth = valid Phase 1 licence token.
| Apply / rollback is performed by the desktop runtime using the signed
| manifest — not by SaaS middleware.
|
*/

Route::prefix('updates')->middleware('throttle:30,1')->group(function () {
    Route::post('/check', [DesktopUpdateController::class, 'check'])
        ->name('updates.check');
    Route::match(['get', 'post'], '/download/{uuid}', [DesktopUpdateController::class, 'download'])
        ->where('uuid', '[0-9a-fA-F\\-]{36}')
        ->name('updates.download');
});
