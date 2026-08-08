<?php

namespace Pressing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;
use Pressing\Http\Controllers\PressingOrderPrintController;
use Pressing\Http\Controllers\PressingOrderQrController;
use Pressing\Http\Controllers\LavageRelancesPrintController;
use Pressing\Http\Livewire\Agences\AgencesIndex;
use Pressing\Http\Livewire\Consumables\ConsumablesIndex;
use Pressing\Http\Livewire\Clients\ClientsIndex;
use Pressing\Http\Livewire\Deliveries\DeliveriesIndex;
use Pressing\Http\Livewire\FinProduction\FinProductionIndex;
use Pressing\Http\Livewire\LavageRelances\LavageRelancesIndex;
use Pressing\Http\Livewire\Loyalty\LoyaltyIndex;
use Pressing\Http\Livewire\Orders\OrdersCreate;
use Pressing\Http\Livewire\Orders\OrdersIndex;
use Pressing\Http\Livewire\Orders\OrdersTri;
use Pressing\Http\Livewire\Reports\ReportsIndex;
use Pressing\Http\Livewire\Settings\ArticleTypesIndex;
use Pressing\Http\Livewire\Settings\DelaysSettings;
use Pressing\Http\Livewire\Settings\LoyaltySettings;
use Pressing\Http\Livewire\Settings\MessagesSettings;
use Pressing\Http\Livewire\Settings\NotificationsSettings;
use Pressing\Http\Livewire\Settings\PaymentMethodsSettings;
use Pressing\Http\Livewire\Settings\PricesIndex;
use Pressing\Http\Livewire\Settings\SettingsHub;
use Pressing\Http\Livewire\Settings\TaxesSettings;
use Pressing\Http\Livewire\Workflow\KanbanBoard;
use Pressing\Http\Livewire\Workflow\WorkflowStagesIndex;
use Pressing\Models\Agence;
use Pressing\Models\PressingClient;
use Pressing\Models\PressingOrder;

class PressingServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'agences';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'pressing');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'pressing-migrations');

        Livewire::component('pressing.agences-index', AgencesIndex::class);
        Livewire::component('pressing.clients-index', ClientsIndex::class);
        Livewire::component('pressing.consumables-index', ConsumablesIndex::class);
        Livewire::component('pressing.orders-index', OrdersIndex::class);
        Livewire::component('pressing.orders-create', OrdersCreate::class);
        Livewire::component('pressing.orders-tri', OrdersTri::class);
        Livewire::component('pressing.kanban-board', KanbanBoard::class);
        Livewire::component('pressing.workflow-stages', WorkflowStagesIndex::class);
        Livewire::component('pressing.settings-hub', SettingsHub::class);
        Livewire::component('pressing.article-types', ArticleTypesIndex::class);
        Livewire::component('pressing.prices-index', PricesIndex::class);
        Livewire::component('pressing.delays-settings', DelaysSettings::class);
        Livewire::component('pressing.taxes-settings', TaxesSettings::class);
        Livewire::component('pressing.messages-settings', MessagesSettings::class);
        Livewire::component('pressing.payment-methods', PaymentMethodsSettings::class);
        Livewire::component('pressing.notifications-settings', NotificationsSettings::class);
        Livewire::component('pressing.deliveries-index', DeliveriesIndex::class);
        Livewire::component('pressing.fin-production', FinProductionIndex::class);
        Livewire::component('pressing.reports-index', ReportsIndex::class);
        Livewire::component('pressing.loyalty-index', LoyaltyIndex::class);
        Livewire::component('pressing.loyalty-settings', LoyaltySettings::class);
        Livewire::component('pressing.lavage-relances-index', LavageRelancesIndex::class);

        Route::bind('pressingAgence', fn ($value) => Agence::on('tenant')->findOrFail($value));
        Route::bind('pressingClient', fn ($value) => PressingClient::on('tenant')->findOrFail($value));
        Route::bind('pressingOrder', fn ($value) => PressingOrder::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        // Public customer tracking (QR scan) — no login required, read-only.
        Route::prefix('app')
            ->middleware(['web', 'tenant'])
            ->group(function () {
                Route::get('/pressing-orders/qr/{token}', PressingOrderQrController::class)
                    ->name('tenant.pressing_orders.qr');
            });

        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/agences', AgencesIndex::class)
                    ->middleware(['module:agences'])
                    ->name('tenant.agences.index');

                Route::get('/pressing-clients', ClientsIndex::class)
                    ->middleware(['module:pressing_clients'])
                    ->name('tenant.pressing_clients.index');

                Route::get('/pressing-lavage-relances', LavageRelancesIndex::class)
                    ->middleware(['module:pressing_lavage_relances'])
                    ->name('tenant.pressing_lavage_relances.index');

                Route::get('/pressing-lavage-relances/print', LavageRelancesPrintController::class)
                    ->middleware(['module:pressing_lavage_relances'])
                    ->name('tenant.pressing_lavage_relances.print');

                Route::get('/pressing-orders', OrdersIndex::class)
                    ->middleware(['module:pressing_orders'])
                    ->name('tenant.pressing_orders.index');

                Route::get('/pressing-orders/create', OrdersCreate::class)
                    ->middleware(['module:pressing_orders'])
                    ->name('tenant.pressing_orders.create');

                Route::get('/pressing-orders/{pressingOrder}/edit', OrdersCreate::class)
                    ->middleware(['module:pressing_orders'])
                    ->name('tenant.pressing_orders.edit');

                Route::get('/pressing-orders/{pressingOrder}/tri', OrdersTri::class)
                    ->middleware(['module:pressing_orders'])
                    ->name('tenant.pressing_orders.tri');

                Route::get('/pressing-orders/{pressingOrder}/print', PressingOrderPrintController::class)
                    ->middleware(['module:pressing_orders'])
                    ->name('tenant.pressing_orders.print');

                Route::get('/pressing-workflow', KanbanBoard::class)
                    ->middleware(['module:pressing_workflow'])
                    ->name('tenant.pressing_workflow.index');

                Route::get('/pressing-workflow/stages', WorkflowStagesIndex::class)
                    ->middleware(['module:pressing_workflow'])
                    ->name('tenant.pressing_workflow.stages');

                Route::get('/pressing-fin-production', FinProductionIndex::class)
                    ->middleware(['module:pressing_fin_production'])
                    ->name('tenant.pressing_fin_production.index');

                Route::get('/pressing-deliveries', DeliveriesIndex::class)
                    ->middleware(['module:pressing_deliveries'])
                    ->name('tenant.pressing_deliveries.index');

                Route::get('/pressing-reports', ReportsIndex::class)
                    ->middleware(['module:pressing_reports'])
                    ->name('tenant.pressing_reports.index');

                Route::get('/pressing-consumables', ConsumablesIndex::class)
                    ->middleware(['module:pressing_consumables'])
                    ->name('tenant.pressing_consumables.index');

                Route::get('/pressing-loyalty', LoyaltyIndex::class)
                    ->middleware(['module:pressing_loyalty'])
                    ->name('tenant.pressing_loyalty.index');

                Route::middleware(['module:pressing_settings'])->group(function () {
                    Route::get('/pressing-settings', SettingsHub::class)
                        ->name('tenant.pressing_settings.index');
                    Route::get('/pressing-settings/article-types', ArticleTypesIndex::class)
                        ->name('tenant.pressing_settings.article_types');
                    Route::get('/pressing-settings/prices', PricesIndex::class)
                        ->name('tenant.pressing_settings.prices');
                    Route::get('/pressing-settings/delays', DelaysSettings::class)
                        ->name('tenant.pressing_settings.delays');
                    Route::get('/pressing-settings/taxes', TaxesSettings::class)
                        ->name('tenant.pressing_settings.taxes');
                    Route::get('/pressing-settings/messages', MessagesSettings::class)
                        ->name('tenant.pressing_settings.messages');
                    Route::get('/pressing-settings/payments', PaymentMethodsSettings::class)
                        ->name('tenant.pressing_settings.payments');
                    Route::get('/pressing-settings/notifications', NotificationsSettings::class)
                        ->name('tenant.pressing_settings.notifications');
                    Route::get('/pressing-settings/loyalty', LoyaltySettings::class)
                        ->name('tenant.pressing_settings.loyalty');
                });
            });
    }
}
