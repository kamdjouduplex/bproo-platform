<?php

namespace InovCom\Dms;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Dms\Http\Livewire\DocumentsIndex;
use InovCom\Dms\Http\Livewire\DocumentUpload;
use InovCom\Dms\Http\Livewire\EntityDocuments;
use InovCom\Dms\Models\Document;
use InovCom\Dms\Services\StorageService;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class DmsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'dms';

    public function register(): void
    {
        $this->app->singleton(StorageService::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-dms');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-dms-migrations');

        Livewire::component('inovcom-dms.documents-index', DocumentsIndex::class);
        Livewire::component('inovcom-dms.document-upload', DocumentUpload::class);
        // EntityDocuments is embedded in other modules — always register it
        Livewire::component('inovcom-dms.entity-documents', EntityDocuments::class);

        Route::bind('document', fn ($value) => Document::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/dms', DocumentsIndex::class)
                    ->middleware(['module:dms'])
                    ->name('tenant.dms.index');
                Route::get('/dms/upload', DocumentUpload::class)
                    ->middleware(['module:dms'])
                    ->name('tenant.dms.upload');
                Route::get('/dms/{document}/download', [DocumentsIndex::class, 'download'])
                    ->middleware(['module:dms'])
                    ->name('tenant.dms.download');
            });
    }
}
