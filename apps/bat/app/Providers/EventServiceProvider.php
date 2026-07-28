<?php

namespace App\Providers;

use App\Events\InvoiceOverdue;
use InovCom\Logistique\Events\DeliveryCompleted;
use InovCom\Logistique\Listeners\DeductStockFromDelivery;
use App\Events\ModuleEvents\ClientCreated;
use App\Events\ModuleEvents\ClientUpdated;
use App\Events\ModuleEvents\ItemCreated;
use App\Events\ModuleEvents\ItemDeleted;
use App\Events\ModuleEvents\ItemUpdated;
use App\Events\ProjectCreatedFromQuote;
use App\Events\QuoteAccepted;
use App\Events\QuoteSent;
use App\Events\WorkflowTransitioned;
use App\Listeners\CreateProjectFromQuote;
use App\Listeners\HandleWorkflowTransitioned;
use App\Listeners\LogClientActivity;
use App\Listeners\LogItemActivity;
use App\Listeners\LogWorkflowTransition;
use App\Listeners\NotifyProjectCreated;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // ── Generic workflow (fires on every transitionTo() call) ─────
        WorkflowTransitioned::class => [
            LogWorkflowTransition::class,       // Phase 0: structured log entry
            HandleWorkflowTransitioned::class,  // Phase 1: dispatches specific events
        ],

        // ── Quote domain events ───────────────────────────────────────
        QuoteAccepted::class => [
            CreateProjectFromQuote::class,      // ★ The flagship automation
        ],
        QuoteSent::class => [
            // Phase 2: \App\Listeners\SendQuoteEmailToClient::class,
        ],

        // ── Project domain events ─────────────────────────────────────
        ProjectCreatedFromQuote::class => [
            NotifyProjectCreated::class,
        ],

        // ── Invoice domain events ─────────────────────────────────────
        InvoiceOverdue::class => [
            // Phase 2: \App\Listeners\SendDunningEmail::class,
        ],

        // ── Logistique domain events ──────────────────────────────────
        DeliveryCompleted::class => [
            DeductStockFromDelivery::class, // deducts stock from source warehouse for each item
        ],

        // ── Legacy module events (Phase 0) ────────────────────────────
        ClientCreated::class => [LogClientActivity::class],
        ClientUpdated::class => [LogClientActivity::class],
        ItemCreated::class   => [LogItemActivity::class],
        ItemUpdated::class   => [LogItemActivity::class],
        ItemDeleted::class   => [LogItemActivity::class],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
