<?php

namespace School;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;
use School\Http\Livewire\SchoolHub;

class SchoolServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'school';

    public function register(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'bproo-school-migrations');
    }

    public function boot(): void
    {
        if (! $this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'school');

        Livewire::component('school.hub', SchoolHub::class);

        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant', 'module:school'])
            ->group(function () {
                Route::get('/school', SchoolHub::class)->name('tenant.school.hub');
            });
    }
}

