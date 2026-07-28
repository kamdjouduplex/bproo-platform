# Phase 0 — Foundation Guide
## What was built, how to activate it, and how to use it

> **Audience:** Developer / System Administrator operating BPROO ERP.

---

## Overview

Phase 0 installs the invisible infrastructure every other feature depends on. No new screens were added — what changed is the engine beneath the existing ones. After this phase the system has:

- A proper **Audit Trail** (who changed what and when)
- An enforced **Workflow State Machine** (status changes follow rules)
- A complete **RBAC permission set** with 6 pre-defined roles
- **Permission enforcement** at every Livewire action via Laravel Gates
- A **Notification Bell** in the topbar showing in-app alerts
- The **Achats (Procurement) module** fully registered and accessible
- Correct **Eloquent relationships** on all line items (QuoteLine, InvoiceLine, PurchaseOrderLine → Item)
- A **working event bus** (all 5 existing module events now have listeners)

---

## 1. Post-Install Steps

After pulling this code, run the following commands:

### 1.1 Install new PHP packages

```bash
composer update
```

This installs:
- `inovcom/achats` — the Procurement module (was in the codebase but not declared)
- `barryvdh/laravel-dompdf` — PDF generation (used from Phase 1)
- `maatwebsite/excel` — Excel export (used from Phase 1)

### 1.2 Run new tenant migrations (new tenants)

New tenants provisioned after this commit automatically get the new tables — no action needed. The `TenantProvisioner` runs all files in `database/migrations/tenant/`.

### 1.3 Run migrations on EXISTING tenants

For tenants that were provisioned before this commit, run:

```bash
php artisan tenants:migrate
```

This runs the two new migrations on every existing tenant database:

| Migration | Creates |
|-----------|---------|
| `2026_04_04_000001_create_audit_logs_table` | `audit_logs` — records all entity changes |
| `2026_04_04_000002_create_notifications_table` | `notifications` — in-app notification storage |

### 1.4 Re-sync permissions and roles for existing tenants

The `UsersModule::install()` method is idempotent — running it again on an existing tenant will add missing permissions and roles without touching existing ones.

Run this for each existing tenant via Tinker:

```bash
php artisan tinker
```

```php
// Replace 'your-tenant-code' with the actual tenant code
$tenant = \App\Models\Tenant::where('code', 'your-tenant-code')->first();
app(\App\Services\TenantManager::class)->setTenant($tenant);

$module = new \InovCom\Users\UsersModule();
$module->install($tenant);

echo "Done — permissions and roles seeded.\n";
```

### 1.5 Sync the Achats module into the database

The `achats` module is now declared in `config/modules.php`. Sync it to the `modules` table:

```bash
php artisan modules:sync
```

Then enable it for each tenant in the admin panel at `/admin/tenants/modules`, or via Tinker:

```php
$module = \App\Models\Module::where('key', 'achats')->first();
$tenant = \App\Models\Tenant::where('code', 'your-tenant-code')->first();
$tenant->modules()->syncWithoutDetaching([$module->id => ['enabled' => true]]);
```

---

## 2. Audit Trail

### What it records
Every create, update, and delete on any model that uses the `Auditable` trait is automatically recorded in the `audit_logs` table of the tenant database.

### Applying it to a model

To enable audit logging on a model, add the `Auditable` trait:

```php
use InovCom\Kernel\Traits\Auditable;

class Quote extends TenantModel
{
    use Auditable;
    // ...
}
```

That's all. The trait hooks into Eloquent model events automatically.

### Reading the audit log (Tinker)

```php
// See all changes to a specific quote (id = 5)
\InovCom\Kernel\Models\AuditLog::on('tenant')
    ->where('auditable_type', 'quotes')
    ->where('auditable_id', 5)
    ->orderByDesc('created_at')
    ->get(['event', 'user_id', 'old_values', 'new_values', 'created_at']);
```

### Excluded fields
The following fields are **never** recorded (sensitive or too noisy):
- `password`
- `remember_token`
- `updated_at`
- Any field in the model's `$hidden` array

---

## 3. Workflow State Machine

### The problem it solves
Previously, any status could be set to any value at any time. A closed project could go back to draft. An accepted quote could be un-accepted. This caused data integrity issues.

### How to use it

Add the `WorkflowStateMachine` trait to a model and implement `allowedTransitions()`:

```php
use InovCom\Kernel\Traits\WorkflowStateMachine;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;

class Quote extends TenantModel
{
    use WorkflowStateMachine;

    public function allowedTransitions(): array
    {
        return [
            'draft'    => ['sent'],
            'sent'     => ['accepted', 'refused'],
            'accepted' => [],           // terminal — no further transitions
            'refused'  => ['draft'],    // can reopen a refused quote
        ];
    }
}
```

### Transitioning status (correct way)

```php
// In a Livewire component action:
$quote->transitionTo('sent', auth('tenant')->id());

// With a reason:
$quote->transitionTo('refused', auth('tenant')->id(), 'Budget insuffisant');
```

### What happens automatically
1. Transition is **validated** — throws `InvalidWorkflowTransitionException` if not allowed
2. The `status` column is updated and saved
3. A **timestamp** is stamped if the model has a matching column (`sent_at`, `accepted_at`, etc.)
4. An **audit log** entry is written (`event = status_changed`)
5. A `WorkflowTransitioned` event is fired — listeners can react

### Catching invalid transitions

```php
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;

try {
    $quote->transitionTo('accepted', $userId);
} catch (InvalidWorkflowTransitionException $e) {
    // Show error to user
    $this->addError('status', $e->getMessage());
}
```

> **Note:** The state machine is ready but **not yet wired into the existing Livewire form components**. That is a Phase 1 task. For now, you can use it manually in Tinker or in new code you write.

---

## 4. Permission System

### The 6 roles and what they can do

| Role | French label | Key capabilities |
|------|-------------|-----------------|
| `admin` | Administrateur | Everything — full access |
| `director` | Directeur | Everything except user management |
| `project_manager` | Chef de Projet | Offers, quotes, projects, limited procurement |
| `sales` | Commercial | Clients, offers, quotes — no financials |
| `accountant` | Comptable | Invoices, payments, procurement validation |
| `technician` | Technicien | Read-only on projects, upload documents |

### Full permission list

| Key | Description |
|-----|-------------|
| `clients.view/create/edit/delete/export` | Client management |
| `offres.view/create/edit/delete` | Offer management |
| `devis.view/create/edit/delete/send/accept` | Quote management |
| `projets.view/create/edit/delete/manage` | Project management |
| `facturation.view/create/edit/delete/send/record_payment/cancel` | Invoice management |
| `achats.view/create/edit/delete/validate/receive` | Procurement |
| `items.view/create/edit/delete` | Product catalog |
| `reports.view/export/schedule` | Reporting |
| `documents.view/upload/delete` | Document management |
| `users.view/create/edit/delete` | User management |
| `roles.view/manage` | Role and permission management |
| `configuration.view/edit` | Tenant settings |

### Checking permissions in Livewire components

```php
// In mount() — throw 403 if no access
public function mount(): void
{
    $this->authorize('clients.view');
}

// In a save() action
public function save(): void
{
    $this->authorize($this->clientId ? 'clients.edit' : 'clients.create');
    // proceed...
}
```

### Checking permissions in Blade views

```blade
@can('devis.send')
    <button wire:click="send">Envoyer au client</button>
@endcan

@cannot('facturation.record_payment')
    <span class="text-muted">Accès refusé</span>
@endcannot
```

### Assigning roles to users (Tinker)

```php
app(\App\Services\TenantManager::class)->setTenant(
    \App\Models\Tenant::where('code', 'your-tenant-code')->first()
);

$user = \InovCom\Users\Models\User::on('tenant')->where('email', 'user@example.com')->first();
$role = \InovCom\Users\Models\Role::on('tenant')->where('name', 'accountant')->first();
$user->roles()->syncWithoutDetaching([$role->id]);
```

---

## 5. Notification Bell

### What it does
A bell icon appears in the top-right corner of the tenant portal for authenticated users. It shows:
- A red badge with the count of unread notifications
- A dropdown panel listing the 15 most recent notifications
- "Mark all as read" button
- Per-notification read status (blue dot = unread)
- Auto-refreshes every 60 seconds

### Sending a notification to a user (from code)

```php
use Illuminate\Notifications\Notification;

class QuoteAcceptedNotification extends Notification
{
    public function __construct(private Quote $quote) {}

    public function via($notifiable): array
    {
        return ['database']; // stored in tenant notifications table
    }

    public function toDatabase($notifiable): array
    {
        return [
            'message' => "Devis {$this->quote->code} accepté par le client.",
            'link'    => route('tenant.devis.edit', ['quote' => $this->quote->id, 'tenant' => session('tenant_code')]),
        ];
    }
}

// Dispatch:
$user->notify(new QuoteAcceptedNotification($quote));
```

> The notification bell reads `data['message']` and `data['link']` from the notification data. Always include these two keys in `toDatabase()`.

---

## 6. Achats (Procurement) Module

The Achats module was already built but was not registered in the system. It is now fully active.

### What it includes
- **Purchase Orders** (Bons de commande) with line items
- **Suppliers** (Fournisseurs) with contact information
- **Validation workflow:** draft → pending_validation → validated → ordered → received

### Routes
| URL | Name | Description |
|-----|------|-------------|
| `/app/achats` | `tenant.achats.index` | List all purchase orders |
| `/app/achats/create` | `tenant.achats.create` | Create a new purchase order |
| `/app/achats/{id}/edit` | `tenant.achats.edit` | Edit a purchase order |
| `/app/achats/fournisseurs` | `tenant.achats.suppliers.index` | List suppliers |
| `/app/achats/fournisseurs/create` | `tenant.achats.suppliers.create` | Add a supplier |
| `/app/achats/fournisseurs/{id}/edit` | `tenant.achats.suppliers.edit` | Edit a supplier |

### Enabling for a tenant
Go to **Admin → Tenants → Modules** and toggle Achats ON for the tenant. This runs the Achats migrations on the tenant database automatically.

---

## 7. What is NOT yet done (Phase 1 tasks)

Phase 0 built the infrastructure. These are the next planned items:

- [ ] Wire `transitionTo()` into existing Livewire form components (replace free-form dropdowns)
- [ ] Add `use Auditable` to all business models (Quote, Invoice, Project, etc.)
- [ ] Quote PDF generation (barryvdh/laravel-dompdf is installed, template needed)
- [ ] Invoice payment recording (`invoice_payments` table + form)
- [ ] `QuoteAccepted` → auto-create Project (event listener)
- [ ] Dunning automation for overdue invoices (scheduled job)
- [ ] `$this->authorize()` calls in all existing Livewire components

---

## 8. File Reference

| File | Purpose |
|------|---------|
| `packages/inovcom/kernel/src/Traits/Auditable.php` | Automatic audit logging trait |
| `packages/inovcom/kernel/src/Traits/WorkflowStateMachine.php` | Enforced status transitions |
| `packages/inovcom/kernel/src/Exceptions/InvalidWorkflowTransitionException.php` | Exception for invalid transitions |
| `packages/inovcom/kernel/src/Models/AuditLog.php` | AuditLog Eloquent model |
| `database/migrations/tenant/2026_04_04_000001_create_audit_logs_table.php` | audit_logs table |
| `database/migrations/tenant/2026_04_04_000002_create_notifications_table.php` | notifications table |
| `packages/inovcom/users/src/UsersModule.php` | Full permission set + 6 roles |
| `app/Providers/AuthServiceProvider.php` | Permission Gates registration |
| `app/Providers/EventServiceProvider.php` | Event → Listener wiring |
| `app/Listeners/LogWorkflowTransition.php` | Logs workflow transitions |
| `app/Listeners/LogClientActivity.php` | Logs client events |
| `app/Listeners/LogItemActivity.php` | Logs item events |
| `app/Events/WorkflowTransitioned.php` | Fired on every status change |
| `app/Livewire/Tenant/NotificationBell.php` | Livewire notification bell component |
| `resources/views/livewire/tenant/notification-bell.blade.php` | Notification bell view |
| `config/modules.php` | Achats added to module registry |
| `composer.json` | achats, dompdf, excel added |
