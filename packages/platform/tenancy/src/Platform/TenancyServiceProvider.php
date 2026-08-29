<?php

namespace Bproo\Platform\Tenancy;

use App\Services\TenantManager;
use Bproo\Platform\Tenancy\Console\AdoptExistingTenantCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $default = $this->app['config']->get('database.default');
        if (is_string($default) && $default !== '' && $default !== 'tenant') {
            $this->app['config']->set('database.central', $default);
        }

        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });
    }

    public function boot(): void
    {
        $views = __DIR__.'/../../resources/views';
        if (is_dir($views)) {
            $this->loadViewsFrom($views, 'platform-tenancy');
        }

        Route::middleware('api')
            ->group(__DIR__.'/../../routes/internal.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AdoptExistingTenantCommand::class,
            ]);
        }
    }
}
