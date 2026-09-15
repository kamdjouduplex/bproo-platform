<?php

namespace Bproo\Platform\Billing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Licence + update APIs are Control Center only — product hosts keep SaaS untouched.
        if ((string) env('APP_PRODUCT_KEY') === 'control-center') {
            Route::prefix('api')
                ->middleware('api')
                ->group(__DIR__.'/../../routes/licence.php');

            Route::prefix('api')
                ->middleware('api')
                ->group(__DIR__.'/../../routes/updates.php');

            Route::prefix('api')
                ->middleware('api')
                ->group(__DIR__.'/../../routes/sync.php');
        }
    }
}
