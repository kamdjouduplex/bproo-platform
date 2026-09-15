<?php

namespace Bproo\Platform\Desktop;

use Bproo\Platform\Desktop\Console\ActivateCommand;
use Bproo\Platform\Desktop\Console\EnqueueCommand;
use Bproo\Platform\Desktop\Console\HeartbeatCommand;
use Bproo\Platform\Desktop\Console\LicenceStatusCommand;
use Bproo\Platform\Desktop\Console\SyncOutCommand;
use Bproo\Platform\Desktop\Console\UpdatesApplyCommand;
use Bproo\Platform\Desktop\Console\UpdatesCheckCommand;
use Bproo\Platform\Desktop\Http\Middleware\EnsureDesktopLicence;
use Illuminate\Support\ServiceProvider;

class DesktopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/desktop.php', 'desktop');
    }

    public function boot(): void
    {
        if (! (bool) config('desktop.enabled', false) && ! (bool) env('DESKTOP_RUNTIME', false)) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/desktop.php' => config_path('desktop.php'),
        ], 'desktop-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'desktop');

        $router = $this->app['router'];
        $router->aliasMiddleware('desktop.licence', EnsureDesktopLicence::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ActivateCommand::class,
                HeartbeatCommand::class,
                SyncOutCommand::class,
                UpdatesCheckCommand::class,
                UpdatesApplyCommand::class,
                EnqueueCommand::class,
                LicenceStatusCommand::class,
            ]);
        }
    }
}
