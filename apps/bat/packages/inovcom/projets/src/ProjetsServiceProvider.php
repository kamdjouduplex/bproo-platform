<?php

namespace InovCom\Projets;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Projets\Http\Livewire\PrestationsIndex;
use InovCom\Projets\Http\Livewire\ProjectForm;
use InovCom\Projets\Http\Livewire\ProjectsIndex;
use InovCom\Projets\Models\Project;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class ProjetsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'projets';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-projets');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-projets-migrations');

        Livewire::component('inovcom-projets.projects-index', ProjectsIndex::class);
        Livewire::component('inovcom-projets.prestations-index', PrestationsIndex::class);
        Livewire::component('inovcom-projets.project-form', ProjectForm::class);

        Route::bind('project', fn ($value) => Project::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/projets', ProjectsIndex::class)
                    ->middleware(['module:projets'])
                    ->name('tenant.projets.index');
                Route::get('/prestations', PrestationsIndex::class)
                    ->middleware(['module:projets'])
                    ->name('tenant.prestations.index');
                Route::get('/projets/create', ProjectForm::class)
                    ->middleware(['module:projets'])
                    ->name('tenant.projets.create');
                Route::get('/prestations/create', ProjectForm::class)
                    ->middleware(['module:projets'])
                    ->name('tenant.prestations.create');
                Route::get('/projets/{project}/edit', ProjectForm::class)
                    ->middleware(['module:projets'])
                    ->name('tenant.projets.edit');
            });
    }
}
