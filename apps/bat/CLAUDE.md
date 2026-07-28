# CLAUDE.md — InovCom ERP System

Multi-tenant SaaS ERP. Laravel 10 · Livewire 4 · PostgreSQL · Package-based modular architecture.
Language: French UI, English code.

---

## Tech Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 10, PHP 8.1+ |
| Frontend | Livewire 4 (no Inertia, no Vue, no React) |
| CSS | Tailwind CSS v3.4 + `@layer components` custom classes in `resources/css/app.css` |
| Database | PostgreSQL (port 5433 in dev) |
| PDF | barryvdh/laravel-dompdf |
| Notifications (toasts) | mckenziearts/laravel-notify |
| Auth | Two guards: `web` (admin) · `tenant` (tenant users) |

---

## Repository Layout

```
erp-system/
├── app/
│   ├── Http/Middleware/        # SetTenantConnection, ApplyTenantSettings, EnsureModuleEnabled
│   ├── Livewire/Admin/         # Admin-side Livewire components
│   ├── Models/                 # Landlord models: Tenant, Module, User
│   └── Services/               # TenantManager, ModuleManager, ModuleRegistry
├── config/modules.php          # Module registry (see below)
├── database/
│   ├── migrations/             # Landlord schema
│   └── migrations/tenant/      # Tenant core schema (published from packages)
├── packages/inovcom/           # All 14 modules (see below)
├── resources/css/app.css       # Tailwind directives + @layer components custom classes
└── resources/views/layouts/    # layouts/app.blade.php
```

---

## Modules (packages/inovcom/)

| Key | Package | Status |
|---|---|---|
| kernel | Core traits, base models, contracts | Always loaded |
| users | Users, roles, permissions | Core |
| clients | Client master data | Core |
| offres | Offers / opportunities | Core |
| devis | Quotations (draft→sent→accepted→refused) | Core |
| projets | Projects | Core |
| facturation | Invoices & payments | Core |
| achats | Purchase orders | Core |
| maintenance | SLA & maintenance orders | Core |
| dms | Document management | Core |
| planning | Calendar & scheduling | Core |
| suivi | Field reports & site supervision | Core |
| branding | Tenant customisation | Core |
| items | Product/service catalogue | **Optional** |

### Package folder structure (every module follows this)

```
packages/inovcom/{module}/
├── composer.json
├── database/migrations/        # Published under tag inovcom-{module}-migrations
├── resources/views/livewire/   # Blade views namespaced inovcom-{module}::
└── src/
    ├── {Module}Module.php      # implements ModuleLifecycle (install/uninstall hooks)
    ├── {Module}ServiceProvider.php
    ├── Http/
    │   ├── Controllers/        # Non-Livewire controllers (e.g. PDF)
    │   └── Livewire/           # All interactive pages
    └── Models/
```

---

## Database — Two Connections

```
landlord  →  default pgsql  →  Tenant, Module, AdminUser …
tenant    →  dynamic pgsql  →  per-tenant DB, switched per request
```

### Rules

- **All module models extend `TenantModel`** (sets `$connection = 'tenant'` automatically).
- Always query tenant models as: `Quote::on('tenant')->...` — never assume a default connection.
- Get the current tenant: `app(TenantManager::class)->tenant()` — **never** `app()->bound('tenant')`.
- Tenant settings: `$tenant->getSetting('key', $default)` / `$tenant->setSetting('key', $value)`.

### Migrations

- Landlord: `database/migrations/`
- Tenant core: `database/migrations/tenant/` (published from packages, always run on provision)
- Tenant optional: `database/migrations/tenant_modules/` (run at module install)

---

## Livewire Components

### Registration (in each ServiceProvider)

```php
Livewire::component('inovcom-devis.quote-form',    QuoteForm::class);
Livewire::component('inovcom-devis.quotes-index',  QuotesIndex::class);
```

### Component anatomy

```php
class QuoteForm extends Component
{
    use AuthorizesWithTenant;   // always include for any write operation

    public function mount(?Quote $quote = null): void
    {
        $this->tenantAuthorize('devis.view');   // gate check, throws 403
        // ...
    }

    public function save(): void
    {
        $this->tenantAuthorize('devis.edit');
        $this->validate();
        // ...
        notify()->success(__('Devis mis à jour.'));
    }

    public function render()
    {
        return view('inovcom-devis::livewire.quotes.form', [...])
            ->layout('layouts.app', ['title' => __('Devis'), 'subtitle' => $this->code]);
    }
}
```

### Key traits

| Trait | Where | Purpose |
|---|---|---|
| `AuthorizesWithTenant` | `app/Livewire/Concerns/` | `tenantAuthorize($ability)` / `tenantCan($ability)` using `auth('tenant')` guard |
| `WorkflowStateMachine` | `kernel/src/Traits/` | `transitionTo($status, $userId)` — validates transitions, stamps timestamps, fires event, writes audit |
| `LazyModuleBoot` | `kernel/src/Traits/` | `shouldBootModule()` — skips booting if module disabled for tenant |

---

## Routes

All tenant routes live in `registerTenantRoutes()` inside each ServiceProvider:

```php
Route::prefix('app')
    ->middleware(['web', 'tenant', 'auth:tenant'])
    ->group(function () {
        Route::get('/devis',              QuotesIndex::class)->middleware('module:devis')->name('tenant.devis.index');
        Route::get('/devis/create',       QuoteForm::class)  ->middleware('module:devis')->name('tenant.devis.create');
        Route::get('/devis/{quote}/edit', QuoteForm::class)  ->middleware('module:devis')->name('tenant.devis.edit');
    });

// Implicit model binding scoped to tenant DB:
Route::bind('quote', fn ($v) => Quote::on('tenant')->findOrFail($v));
```

**Naming pattern:** `tenant.{module}.{action}` (index / create / edit / show / pdf …)

**Route helper in Blade:**
```blade
route('tenant.devis.index', ['tenant' => $tenantCode])
```
Always pass `['tenant' => $tenantCode]`. Get `$tenantCode` with:
```php
$tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
```

---

## Middleware Stack

| Alias | Class | Purpose |
|---|---|---|
| `tenant` | `SetTenantConnection` | Resolves tenant from route prefix, validates active, switches DB |
| `module:{key}` | `EnsureModuleEnabled` | 404 if module disabled for this tenant |
| `auth:tenant` | Laravel built-in | Requires authenticated tenant user |

`ApplyTenantSettings` runs in the web group: sets locale (`fr` default), currency (`XOF` default via `config('inovcom.currency')`), timezone.

---

## Workflow State Machine

Models using workflows must implement `allowedTransitions()` and use `transitionTo()`:

```php
// In model:
public function allowedTransitions(): array
{
    return [
        'draft'    => ['sent'],
        'sent'     => ['accepted', 'refused', 'draft'],
        'accepted' => [],
        'refused'  => ['draft'],
    ];
}

// In Livewire:
try {
    $quote->transitionTo('sent', auth('tenant')->id());
} catch (InvalidWorkflowTransitionException $e) {
    notify()->error($e->getMessage());
}
```

Transition auto-stamps matching timestamp columns (`sent_at`, `accepted_at`, etc.) and writes to audit log.

---

## Module Registry (config/modules.php)

Each entry:

```php
'devis' => [
    'core'              => true,          // always loaded vs optional
    'label'             => 'Devis',
    'route_name'        => 'tenant.devis.index',
    'lifecycle_handler' => DevisModule::class,
    'enabled_by_default'=> true,
    'group'             => 'commercial',  // sidebar group
    'menu_order'        => 35,
    'permission'        => 'devis.view',
],
```

Check if module is on: `app(ModuleRegistry::class)->isEnabled('items', $tenant)`.

---

## UI / CSS

**Use Tailwind CSS v3.4.** The project was fully migrated from custom utility CSS to Tailwind in 2026. All blade files use Tailwind classes directly. `resources/css/app.css` contains the Tailwind directives (`@tailwind base/components/utilities`) plus `@layer components` definitions for the shared component classes below.

### Component classes (defined via `@layer components` in app.css)

```
Layout      card · page-body · page-actions
Forms       field · field-label · field-error · input · input-sm
Buttons     btn · btn-primary · btn-secondary · btn-sm · btn-success · btn-danger
Tables      table-action · table-action-edit · table-action-delete
Badges      badge · badge-success · badge-warning · badge-danger · badge-info · badge-secondary
```

These are usable alongside any Tailwind utility class — mix freely.

### Grid / layout pattern

Use Tailwind's grid system directly:
```html
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2"><!-- full-width field --></div>
</div>
```

### Dynamic status/badge classes

Always return **complete class strings** from PHP `match()` — never concatenate partial strings, as Tailwind's JIT purger only detects complete class names:
```php
// CORRECT — full strings
$statusBg = match($status) {
    'draft'     => 'bg-slate-100 text-slate-600',
    'validated' => 'bg-emerald-100 text-emerald-700',
    default     => 'bg-slate-100 text-slate-500',
};

// WRONG — JIT will not detect 'bg-slate-' or 'text-emerald-'
$color = match($status) { 'validated' => 'emerald' };
$class = "bg-{$color}-100";
```

### layouts/app.blade.php

```php
->layout('layouts.app', [
    'title'    => 'Page title',   // shown in topbar
    'subtitle' => 'DEV00001',     // shown after › in topbar (optional)
])
```

---

## Notifications

```php
notify()->success(__('Message.'));
notify()->error(__('Message.'));
notify()->info(__('Message.'));
notify()->warning(__('Message.'));
```

Always use `__()` for user-facing strings (French translations expected).

---

## Livewire Gotchas

- **Always add `wire:key`** to `@foreach` rows in tables — without it Livewire's DOM diffing breaks click handlers after add/remove.
  ```blade
  <tr wire:key="line-{{ $index }}">
  ```
- **Modals**: Use native `<dialog>` element + Alpine `x-init` with `showModal()` / `close()`. Do **not** use `@teleport` + `@if` (fragile when block starts empty). Do **not** rely on `position:fixed` inside a card (parent may have transforms).
  ```blade
  <dialog id="my-dialog"
      x-data x-init="$watch('$wire.showModal', v => v ? $nextTick(()=>$el.showModal()) : $el.open && $el.close())"
      @cancel.prevent="$wire.call('closeModal')">
  ```
- **Item picker inside quotes**: `QuoteForm` has `openItemPicker()` / `closeItemPicker()` / `addItemFromCatalog($id)`. Items are loaded only when `$showItemPicker === true`.
- **Tenant auth guard**: Use `auth('tenant')->user()` / `auth('tenant')->id()` — never plain `auth()->user()`.

---

## Permissions Naming Convention

```
{module}.view      read-only access
{module}.create    create new records
{module}.edit      update existing records
{module}.delete    delete records
{module}.send      workflow: send to client (devis, facturation)
{module}.accept    workflow: accept/approve
{module}.export    export to PDF/Excel
```

---

## Console Commands

```
php artisan modules:sync          # Sync config/modules.php → Module table (landlord)
php artisan tenants:provision     # Re-run provisioning for pending tenants
```

---

## What NOT to do

- Don't use `app()->bound('tenant')` — use `app(TenantManager::class)->tenant()`.
- Don't set `$model->status = '...'` directly on workflow models — use `transitionTo()`.
- Don't write raw DB queries without `->on('tenant')` on tenant models.
- Don't use inline `style=""` for layout that Tailwind can express — use utility classes instead.
- Don't create new middleware — extend existing ones or add a check inside `EnsureModuleEnabled`.
- Don't skip `wire:key` on Livewire `@foreach` loops.
- Don't use `position:fixed` for modals inside Livewire cards — use `<dialog>` + `showModal()`.
