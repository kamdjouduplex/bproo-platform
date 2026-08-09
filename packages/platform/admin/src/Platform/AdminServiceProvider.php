<?php

namespace Bproo\Platform\Admin;

use App\Services\CompanyIntelligenceService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $views = __DIR__.'/../../resources/views';
        if (is_dir($views)) {
            View::addLocation($views);
            $this->loadViewsFrom($views, 'platform-admin');
        }

        View::composer(['layouts.partials.cc-shell', 'layouts.app'], function ($view) {
            if (! auth()->check()) {
                $view->with('seatAlerts', collect());

                return;
            }

            try {
                $view->with(
                    'seatAlerts',
                    app(CompanyIntelligenceService::class)->tenantsExceedingUsersLimit()
                );
            } catch (\Throwable) {
                $view->with('seatAlerts', collect());
            }
        });
    }
}
