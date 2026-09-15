<?php

use Bproo\Platform\Billing\Http\Controllers\DesktopSyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Desktop OUT sync API (Control Center only)
|--------------------------------------------------------------------------
|
| Phase 3 cloud backup. Auth = Phase 1 licence token.
| Events are stored append-only — not applied to SaaS tenant DBs.
|
*/

Route::prefix('sync')->middleware('throttle:60,1')->group(function () {
    Route::post('/out', [DesktopSyncController::class, 'pushOut'])
        ->name('sync.out');
    Route::post('/out/status', [DesktopSyncController::class, 'status'])
        ->name('sync.out.status');
});
