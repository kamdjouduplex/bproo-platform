<?php

namespace InovCom\Prospects;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Prospects\Http\Livewire\ProspectForm;
use InovCom\Prospects\Http\Livewire\ProspectShow;
use InovCom\Prospects\Http\Livewire\ProspectsIndex;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Services\ProspectsService;
use Livewire\Livewire;

class ProspectsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'prospects';

    /** Also boot routes/UI when the CRM suite module is enabled. */
    protected array $alsoBootWhenModules = ['crm'];

    public function register(): void
    {
        $this->app->singleton(ProspectsService::class);
    }

    public function boot(): void
    {
        if (! $this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-prospects');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-prospects-migrations');

        Livewire::component('inovcom-prospects.prospects-index', ProspectsIndex::class);
        Livewire::component('inovcom-prospects.prospect-form', ProspectForm::class);
        Livewire::component('inovcom-prospects.prospect-show', ProspectShow::class);

        // Only bind when resolving tenant CRM routes. A global bind steals
        // {prospect} from landlord Control Center CRM (platform_prospects).
        Route::bind('prospect', function ($value, $route) {
            if (! str_starts_with((string) $route->getName(), 'tenant.prospects.')) {
                return $value;
            }

            return Prospect::on('tenant')->findOrFail($value);
        });

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/prospects', ProspectsIndex::class)
                    ->middleware(['module:prospects'])
                    ->name('tenant.prospects.index');
                Route::get('/prospects/create', ProspectForm::class)
                    ->middleware(['module:prospects'])
                    ->name('tenant.prospects.create');
                Route::get('/prospects/{prospect}/edit', ProspectForm::class)
                    ->middleware(['module:prospects'])
                    ->name('tenant.prospects.edit');
                Route::get('/prospects/{prospect}', ProspectShow::class)
                    ->middleware(['module:prospects'])
                    ->name('tenant.prospects.show');
            });
    }
}
