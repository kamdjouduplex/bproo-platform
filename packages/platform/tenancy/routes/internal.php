<?php

use App\Http\Controllers\Internal\ProvisionTenantController;
use App\Http\Middleware\VerifyInternalProvisionSecret;
use Illuminate\Support\Facades\Route;

Route::post('/internal/tenants/provision', ProvisionTenantController::class)
    ->middleware(['throttle:30,1', VerifyInternalProvisionSecret::class])
    ->name('internal.tenants.provision');
