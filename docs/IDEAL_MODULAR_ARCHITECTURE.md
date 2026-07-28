# Bproo Platform — Ideal Modular Architecture

**Version:** 1.0  
**Date:** 2026-07-28  
**Status:** Target architecture (design only — no code changes)  
**Inputs:** `ARCHITECTURE_AUDIT.md`, `DUPLICATION_REPORT.md`  
**Principles:** Single Responsibility · Open/Closed · Liskov · Interface Segregation · Dependency Inversion

---

## 1. Design Goals

| Goal | Rule |
|---|---|
| Never mix responsibilities | One package = one bounded context |
| Depend on abstractions | Cross-package calls go through Interfaces only |
| Verticals are plugins | Pressing / BAT / Pharmacy never leak into shared packages |
| Host apps stay thin | Apps wire modules + theme; they own almost no domain code |
| Preserve what works | Keep Composer path packages, `ModuleLifecycle`, DB-per-tenant, Livewire UI |
| Evolve without rewrite | Map current `inovcom/*` packages onto this target incrementally |

---

## 2. Monorepo Layout

```
bproo-platform/
├── apps/
│   ├── erp/                 # Retail / POS / pharmacy host
│   ├── pressing/            # Laundry host
│   └── bat/                 # Construction host
├── packages/
│   ├── platform/            # SaaS control plane packages
│   ├── shared/              # Cross-vertical business packages
│   └── verticals/           # Product-specific packages
├── docs/
└── composer.json            # path repositories
```

**Namespace convention:** `Bproo\{Layer}\{Package}\`  
**Composer names:** `bproo/{package-kebab}`

---

## 3. Layered Dependency Rules

```
┌──────────────────────────────────────────────────────────┐
│  apps/*  (composition root: modules.php, theme, routes)  │
└───────────────────────────┬──────────────────────────────┘
                            │ may require
┌───────────────────────────▼──────────────────────────────┐
│  verticals/*   (pressing, bat-*, pharmacy-*)             │
└───────────────────────────┬──────────────────────────────┘
                            │ may require
┌───────────────────────────▼──────────────────────────────┐
│  shared/*      (crm, catalogue, sales, inventory, …)     │
└───────────────────────────┬──────────────────────────────┘
                            │ may require
┌───────────────────────────▼──────────────────────────────┐
│  platform/*    (core, tenancy, auth, users, permissions) │
└──────────────────────────────────────────────────────────┘
```

**Forbidden:**

- `shared` depending on `verticals`
- `platform` depending on `shared` or `verticals`
- Package A importing Package B’s Eloquent models directly (use Interface / DTO / ID)
- UI packages owning financial posting rules of another context

---

## 4. Package Catalogue (complete list)

### 4.1 Platform layer

| Package | Path |
|---|---|
| Core | `packages/platform/core` |
| Tenancy | `packages/platform/tenancy` |
| Auth | `packages/platform/auth` |
| Users | `packages/platform/users` |
| Permissions | `packages/platform/permissions` |
| Settings | `packages/platform/settings` |
| Modules | `packages/platform/modules` |
| Billing | `packages/platform/billing` |
| Admin | `packages/platform/admin` |
| Audit | `packages/platform/audit` |
| Activity Log | `packages/platform/activity-log` |
| Workflow | `packages/platform/workflow` |
| Notifications | `packages/platform/notifications` |
| Media | `packages/platform/media` |
| Printing | `packages/platform/printing` |
| Stores | `packages/platform/stores` |

### 4.2 Shared business layer

| Package | Path |
|---|---|
| CRM | `packages/shared/crm` |
| Catalogue | `packages/shared/catalogue` |
| Inventory | `packages/shared/inventory` |
| Inventory Count | `packages/shared/inventory-count` |
| Suppliers | `packages/shared/suppliers` |
| Purchases | `packages/shared/purchases` |
| Sales | `packages/shared/sales` |
| Cash Register | `packages/shared/cash-register` |
| Quotations | `packages/shared/quotations` |
| Invoicing | `packages/shared/invoicing` |
| Payments | `packages/shared/payments` |
| Debts | `packages/shared/debts` |
| Expenses | `packages/shared/expenses` |
| Losses | `packages/shared/losses` |
| Returns | `packages/shared/returns` |
| Reservations | `packages/shared/reservations` |
| Reports | `packages/shared/reports` |
| HR | `packages/shared/hr` |
| Attendance | `packages/shared/attendance` |
| Tickets | `packages/shared/tickets` |
| Batches | `packages/shared/batches` |
| Prescriptions | `packages/shared/prescriptions` |

### 4.3 Vertical layer

| Package | Path |
|---|---|
| Pressing | `packages/verticals/pressing` |
| BAT Offers | `packages/verticals/bat-offers` |
| BAT Quotes | `packages/verticals/bat-quotes` |
| BAT Projects | `packages/verticals/bat-projects` |
| BAT Maintenance | `packages/verticals/bat-maintenance` |
| BAT Planning | `packages/verticals/bat-planning` |
| BAT Site Tracking | `packages/verticals/bat-site-tracking` |
| BAT Logistics | `packages/verticals/bat-logistics` |
| BAT Purchasing | `packages/verticals/bat-purchasing` |
| BAT Invoicing | `packages/verticals/bat-invoicing` |

> **Note on BAT invoicing/purchasing:** Kept separate from shared `invoicing` / `purchases` because schemas and workflows diverge (audit finding). Both may later implement the same Interfaces (`InvoicingApi`, `PurchasingApi`) without merging tables.

---

## 5. Cross-Cutting Conventions (every package)

| Concern | Standard |
|---|---|
| Service provider | `{Package}ServiceProvider` + `LazyModuleBoot` |
| Lifecycle | `{Package}Module implements ModuleLifecycle` |
| Tenant models | Extend `Bproo\Platform\Core\Models\TenantModel` |
| Authorization | Declare permissions in Module; check via `Permissions` API — **no cross-package Policies** |
| Cross-package I/O | Interfaces in `core` or owning package `Contracts/` |
| Events | Own domain events inside the package; listeners in consumer or app composition |
| Routes | Registered by provider under `/app` + `module:{key}` |
| Views | `resources/views` namespaced `bproo-{package}::` |
| Config | `config/bproo/{package}.php` published optionally |
| Migrations | Tagged `bproo-{package}-migrations` |
| Public API | Only Interfaces + Events + explicitly exported DTOs |

---

## 6. Platform Packages (detailed)

---

### 6.1 `packages/platform/core`

**Responsibilities**  
Foundation only: tenant Eloquent base, shared casts, module lifecycle contract, shared domain Interfaces that multiple packages implement. **No UI. No business rules. No migrations for business tables.**

**Models**  
- `TenantModel` (abstract)  
- (optional) shared value casts only

**Services**  
None.

**Events**  
None owned (defines no domain).

**Interfaces**  
- `ModuleLifecycle`  
- `ItemsApi`  
- `ClientsApi`  
- `BatchesApi`  
- `StockApi`  
- `InvoicingApi`  
- `PurchasingApi`  
- `NotificationChannel`  
- `SettingsStore`

**Routes / Views / Livewire**  
None.

**Database migrations**  
None.

**Configuration**  
`config/bproo/core.php` — locale defaults, money scale, connection name (`tenant`).

**Permissions**  
None.

**Public API**  
Contracts + `TenantModel` + `TrimmedDecimal` cast + `LazyModuleBoot` trait.

**Dependencies**  
`illuminate/support`, `illuminate/database` only.

---

### 6.2 `packages/platform/tenancy`

**Responsibilities**  
Resolve current tenant, configure DB connection, provision/drop tenant databases, store landlord tenant records. **Does not** own SaaS plans (→ Billing) or module install (→ Modules).

**Models** (landlord)  
- `Tenant`  
- `TenantSetting` (key/value; low-level — Settings package is the UX/API façade)

**Services**  
- `TenantManager`  
- `TenantProvisioner`  
- `TenantConnectionFactory`

**Events**  
- `TenantProvisioningStarted`  
- `TenantProvisioned`  
- `TenantProvisioningFailed`  
- `TenantActivated` / `TenantSuspended`

**Interfaces**  
- `TenantResolver`  
- `TenantContext`

**Routes**  
None (middleware only). Optional health endpoint in Admin.

**Views / Livewire**  
None (Admin owns UI).

**Migrations** (landlord)  
- `tenants`  
- `tenant_settings`

**Configuration**  
`config/bproo/tenancy.php` — DB prefix, driver, resolution order (header / query / subdomain).

**Permissions**  
None (system-level).

**Public API**  
`TenantManager::setTenant()`, `currentTenant()`, middleware aliases `tenant`, `tenant.active`.

**Dependencies**  
`core`.

---

### 6.3 `packages/platform/auth`

**Responsibilities**  
Authentication only: login, logout, password reset, session guard wiring for **system** and **tenant** realms. **Does not** manage roles, permissions, or user CRUD.

**Models**  
None new (uses Users package models via contract).

**Services**  
- `AdminAuthService`  
- `TenantAuthService`  
- `PasswordBrokerFactory` (per guard)

**Events**  
- `AdminLoggedIn` / `AdminLoggedOut`  
- `TenantUserLoggedIn` / `TenantUserLoggedOut`  
- `TenantLoginFailed`

**Interfaces**  
- `AuthenticatableUserProvider` (bridge to Users)

**Routes**  
- `/admin/login`, `/admin/logout`  
- `/app/login`, `/app/logout`

**Views**  
Auth Blade layouts (login forms).

**Livewire**  
Optional login forms; or classic controllers (current pattern OK).

**Migrations**  
Landlord: system `users`, `password_reset_tokens`.  
Tenant: none (Users package owns tenant users table).

**Configuration**  
`config/bproo/auth.php` — guards `web`, `tenant`; session keys.

**Permissions**  
None.

**Public API**  
Auth controllers/middleware; guard names stable forever.

**Dependencies**  
`core`, `users` (for tenant user model binding), `tenancy` (tenant must resolve before tenant login).

---

### 6.4 `packages/platform/users`

**Responsibilities**  
Tenant (and optionally system) **user identity**: profile, active flag, store/agency assignment fields as opaque attributes. **Does not** define permission keys or role matrices (→ Permissions).

**Models**  
- `User` (tenant)  
- (system `User` may live here or in Auth — prefer here for single identity module)

**Services**  
- `UserService` (CRUD, activate/deactivate)  
- `UserDirectory` (lookup by id/email)

**Events**  
- `UserCreated`  
- `UserUpdated`  
- `UserDeactivated`

**Interfaces**  
- `UserDirectory`  
- `CurrentUser`

**Routes**  
- `/app/users`, create, edit

**Views**  
User form/index blades.

**Livewire**  
- `UsersIndex`  
- `UserForm`

**Migrations**  
- `users` (tenant)

**Configuration**  
`config/bproo/users.php` — username rules, invite enabled.

**Permissions**  
Declares only identity perms: `users.view`, `users.create`, `users.update`, `users.delete`.

**Public API**  
`UserDirectory`; Eloquent `User` **not** part of public API for other domains (prefer ID + directory).

**Dependencies**  
`core`, `permissions` (to attach roles — via interface, not Role model import preferred).

---

### 6.5 `packages/platform/permissions`

**Responsibilities**  
RBAC: roles, permissions, role↔user, role↔permission, permission catalogue UI, `hasPermission` resolution. **Does not** create users.

**Models**  
- `Role`  
- `Permission`

**Services**  
- `PermissionRegistrar` (install keys from modules)  
- `RoleService`  
- `AccessGate` (`can(user, key)`)  
- `PermissionCatalog`

**Events**  
- `RoleCreated`  
- `PermissionsSynced`  
- `RolePermissionsUpdated`

**Interfaces**  
- `AccessGate`  
- `PermissionRegistrar`

**Routes**  
- `/app/roles`, `/app/permissions`

**Views**  
Roles + permissions matrix.

**Livewire**  
- `RolesIndex`, `RoleForm`, `PermissionsMatrix`

**Migrations**  
- `roles`, `permissions`, `role_user`, `permission_role`

**Configuration**  
`config/bproo/permissions.php` — admin role name, wildcard rules (`*`).

**Permissions**  
`roles.view/create/update/delete`, `permissions.view/manage`.

**Public API**  
`AccessGate::check($user, $ability)`; middleware helpers; **no** direct Permission model use outside this package.

**Dependencies**  
`core`, `users` (pivot to users only).

---

### 6.6 `packages/platform/settings`

**Responsibilities**  
Typed tenant settings façade (identity, locale, currency, tax defaults, branding messages). **Does not** own business documents.

**Models**  
Uses Tenancy `TenantSetting` via `SettingsStore` — or owns `Setting` if split later. Prefer façade over duplicate storage.

**Services**  
- `SettingsRepository`  
- `BrandingSettings`  
- `FinanceDefaults`  
- `RegionalSettings`

**Events**  
- `SettingsUpdated`  
- `BrandingUpdated`

**Interfaces**  
- Implements `SettingsStore`  
- `BrandingReader`

**Routes**  
- `/app/settings`, `/app/branding` (or single hub with tabs)

**Views**  
Settings hub + branding.

**Livewire**  
- `SettingsIndex`  
- `BrandingIndex`

**Migrations**  
None (uses tenant_settings) **or** additive typed columns only if justified.

**Configuration**  
`config/bproo/settings.php` — defaults (XOF, fr, Africa/Douala).

**Permissions**  
`settings.view`, `settings.update`, `branding.update`.

**Public API**  
`SettingsStore::get/set`, `BrandingReader`.

**Dependencies**  
`core`, `tenancy`, `permissions`.

---

### 6.7 `packages/platform/modules`

**Responsibilities**  
Module catalogue, enable/disable per tenant, install/uninstall lifecycle orchestration, version compatibility, marketplace stub. **Does not** contain domain features.

**Models** (landlord)  
- `Module`  
- `TenantModule`  
- `ModuleEvent` (install audit)

**Services**  
- `ModuleRegistry`  
- `ModuleMarketplace`  
- `ModuleVersionService`  
- `ModulesCatalogSync`

**Events**  
- `ModuleInstalled`  
- `ModuleUninstalled`  
- `ModuleInstallFailed`

**Interfaces**  
- `ModuleRegistryContract`  
- uses `ModuleLifecycle` from core

**Routes**  
Admin-only (delegated UI in Admin package): module APIs if needed.

**Views / Livewire**  
Prefer Admin package for UI; this package exposes services only **or** thin Livewire used by Admin.

**Migrations**  
- `modules`, `tenant_modules`, `module_events`

**Configuration**  
Consumed from app `config/modules.php` (composition root) + `config/bproo/modules.php`.

**Permissions**  
System admin only.

**Public API**  
`ModuleRegistry::isEnabled()`, `install()`, `uninstall()`; middleware `module:{key}`.

**Dependencies**  
`core`, `tenancy`.

---

### 6.8 `packages/platform/billing`

**Responsibilities**  
SaaS subscription plans, tenant balance, payments to platform, grace/suspend. **Not** tenant customer invoicing (→ Invoicing / Payments).

**Models** (landlord)  
- `Plan`  
- `Subscription`  
- `TenantPayment`  
- `TenantBalanceTransaction`

**Services**  
- `SubscriptionService`  
- `PlanCatalog`  
- `BillingSuspensionService`

**Events**  
- `SubscriptionActivated`  
- `SubscriptionSuspended`  
- `SubscriptionPaymentRecorded`  
- `TenantBalanceChanged`

**Interfaces**  
- `BillingDriver` (full SaaS vs simple plan string for BAT)  
- `SubscriptionStatusReader`

**Routes**  
Admin subscription management; tenant `/app/subscription`.

**Views / Livewire**  
- `SubscriptionStatus` (tenant)  
- Plan forms live in Admin or here

**Migrations**  
plans, subscriptions, payments, balance transactions.

**Configuration**  
`config/bproo/billing.php` — grace days, currency, cron.

**Permissions**  
Admin system; tenant `subscription.view`.

**Public API**  
`SubscriptionStatusReader::isActive(Tenant)`.

**Dependencies**  
`core`, `tenancy`.

---

### 6.9 `packages/platform/admin`

**Responsibilities**  
Super-admin UI composition: tenants, health, module toggles, plans, analytics shell. **No** tenant business domain screens.

**Models**  
None (uses Tenancy/Modules/Billing models via services).

**Services**  
- `AdminDashboardService`  
- `TenantHealthChecker`

**Events**  
None required.

**Interfaces**  
None (composition UI).

**Routes**  
`/admin/*` (dashboard, tenants, packages, plans, module-events, analytics).

**Views**  
`system/*` layouts.

**Livewire**  
- `Dashboard`, `Tenants`, `TenantForm`, `TenantHealth`, `TenantModules`, `TenantSettings`, `TenantSubscription`, `Plans`, `PlanForm`, `ModuleMarketplace`, `ModuleEvents`, `Analytics`

**Migrations**  
None.

**Configuration**  
`config/bproo/admin.php` — menu, features flags.

**Permissions**  
Implicit system admin.

**Public API**  
None (UI package).

**Dependencies**  
`auth`, `tenancy`, `modules`, `billing`, `settings`.

---

### 6.10 `packages/platform/audit`

**Responsibilities**  
Immutable security/compliance audit trail for sensitive actions (permission changes, module install, money voids). **Not** generic model dirty logging (→ Activity Log).

**Models**  
- `AuditEntry` (who, what, before/after, ip, tenant)

**Services**  
- `AuditLogger`

**Events**  
Listens to security-related events; emits none required.

**Interfaces**  
- `Auditor`

**Routes**  
`/app/audit` (tenant), admin module-events may remain in Modules.

**Views / Livewire**  
- `AuditLogIndex`

**Migrations**  
- `audit_entries`

**Configuration**  
What events are audited.

**Permissions**  
`audit.view`.

**Public API**  
`Auditor::record(...)`.

**Dependencies**  
`core`, `users`, `permissions`.

---

### 6.11 `packages/platform/activity-log`

**Responsibilities**  
Eloquent model change timeline (created/updated/deleted) for CRM/project entities. Separated from Audit (intent differs).

**Models**  
- `Activity` (polymorphic)

**Services**  
- `ActivityRecorder`  
- Observer / `Auditable`-style trait (from BAT kernel)

**Events**  
- `ActivityRecorded`

**Interfaces**  
- `RecordsActivity`

**Routes**  
Embedded widgets; optional `/app/activity`.

**Views / Livewire**  
- `ActivityTimeline` (embeddable)

**Migrations**  
- `activities`

**Configuration**  
Ignored attributes (password, tokens).

**Permissions**  
Usually inherit parent entity view permission.

**Public API**  
Trait + `ActivityRecorder`.

**Dependencies**  
`core`.

---

### 6.12 `packages/platform/workflow`

**Responsibilities**  
Generic state machine: allowed transitions, guards hooks, transition logging. **Does not** define BAT/Pressing statuses — verticals supply transition maps.

**Models**  
Optional `WorkflowTransitionLog`.

**Services**  
- `StateMachine`  
- `TransitionGuard`

**Events**  
- `WorkflowTransitioned`

**Interfaces**  
- `Workflowable`  
- `TransitionMap`

**Routes / Views / Livewire**  
None (library).

**Migrations**  
Optional transition log table.

**Configuration**  
None or debug flags.

**Permissions**  
None.

**Public API**  
`Workflowable` trait / `StateMachine::transition($entity, $to, $actor)`.

**Dependencies**  
`core`, optional `activity-log`.

---

### 6.13 `packages/platform/notifications`

**Responsibilities**  
Multi-channel notification dispatch (database, mail, WhatsApp webhook, SMS webhook), templates registry, delivery logs. **Does not** decide *when* to notify (callers/verticals do).

**Models**  
- `NotificationTemplate`  
- `NotificationLog`  
- uses Laravel `notifications` table for in-app

**Services**  
- `NotificationDispatcher`  
- `TemplateRenderer`  
- Channel drivers: `DatabaseChannel`, `MailChannel`, `WebhookChannel`

**Events**  
- `NotificationDispatched`  
- `NotificationFailed`

**Interfaces**  
- `NotificationChannel`  
- `Notifies`

**Routes**  
`/app/notifications` preferences; bell endpoint.

**Views / Livewire**  
- `NotificationBell`  
- `NotificationSettings`

**Migrations**  
templates, logs, user preferences.

**Configuration**  
`config/bproo/notifications.php` — channels, webhook URLs.

**Permissions**  
`notifications.manage`.

**Public API**  
`NotificationDispatcher::send($templateKey, $notifiable, $payload)`.

**Dependencies**  
`core`, `settings`, `users`.

---

### 6.14 `packages/platform/media`

**Responsibilities**  
File storage, versioning, polymorphic attachments. Replaces BAT DMS as shared capability.

**Models**  
- `Document`  
- `DocumentAttachment`  
- `MediaVersion`

**Services**  
- `StorageService`  
- `AttachmentService`

**Events**  
- `DocumentUploaded`  
- `DocumentDeleted`

**Interfaces**  
- `AttachesMedia`

**Routes**  
`/app/media`, upload, download.

**Views / Livewire**  
- `DocumentsIndex`  
- `DocumentUpload`  
- `EntityDocuments` (embed)

**Migrations**  
documents, attachments, versions.

**Configuration**  
Disks, max size, mime allowlist.

**Permissions**  
`media.view`, `media.upload`, `media.delete`.

**Public API**  
`AttachmentService::attach($model, $file)`.

**Dependencies**  
`core`, `users`, `permissions`.

---

### 6.15 `packages/platform/printing`

**Responsibilities**  
PDF/print helpers: margins, tax line layout, amount-in-words, pagination, DomPDF wrapper. **Does not** own invoice business rules.

**Models**  
None.

**Services**  
- `PdfRenderer`  
- `DocumentTaxCalculator`  
- `DocumentMargin`  
- `CommercialPrintPaginator`  
- `AmountInWords`  
- `PrintDocument`

**Events**  
None.

**Interfaces**  
- `RendersPdf`  
- `PrintableDocument`

**Routes**  
None (used by controllers in domain packages).

**Views**  
Shared print layout partials only.

**Livewire**  
None.

**Migrations**  
None.

**Configuration**  
Paper size, default font, tax display.

**Permissions**  
None.

**Public API**  
`PdfRenderer::view($blade, $data)`.

**Dependencies**  
`core`, `barryvdh/laravel-dompdf`.

---

### 6.16 `packages/platform/stores`

**Responsibilities**  
Multi-store (retail) context inside a tenant: store CRUD, current store, user assignment. **Not** Pressing agences (vertical) and **not** warehouses (Inventory).

**Models**  
- `Store`

**Services**  
- `StoreContextService`  
- `MultiStoreSetupService`

**Events**  
- `StoreChanged`  
- `StoreCreated`

**Interfaces**  
- `StoreContext`

**Routes**  
Store switcher endpoints; admin setup.

**Views / Livewire**  
Store switcher component; optional StoresIndex.

**Migrations**  
- `stores`  
- user `assigned_store_id`

**Configuration**  
Multi-store enabled flag.

**Permissions**  
`stores.view`, `stores.manage`, `stores.view_all`.

**Public API**  
`StoreContext::current()`, middleware `tenant.store`.

**Dependencies**  
`core`, `users`, `permissions`, `tenancy`.

---

## 7. Shared Business Packages (detailed)

---

### 7.1 `packages/shared/crm`

**Responsibilities**  
Customers & leads: clients, contacts, addresses, segments, credit limits, prospects, client notes/documents/reminders. **Single CRM context** (replaces split `clients` + `prospects`). **Does not** post invoices or stock.

**Models**  
`Client`, `Contact`, `Address`, `Segment`, `Zone`, `ClientCategory`, `CreditLimit`, `ClientNote`, `ClientDocument`, `ClientReminder`, `Prospect`, `ProspectActivity`

**Services**  
`ClientsApiService`, `ClientCreditService`, `ClientAgingService`, `ClientDuplicateService`, `ClientCodeGenerator`, `ProspectsService`, `Client360Service`

**Events**  
`ClientCreated`, `ClientUpdated`, `ClientDeleted`, `ProspectConverted`

**Interfaces**  
Implements `ClientsApi`  
`CreditLimitReader`

**Routes**  
`/app/clients`, `/app/clients/{id}`, `/app/prospects`, duplicates tools

**Views**  
CRM blades including 360 workspace.

**Livewire**  
`ClientsIndex`, `ClientsForm`, `ClientShow`, `Client360Show`, `ClientsDuplicates`, `ProspectsIndex`, `ProspectForm`, `ProspectShow`

**Migrations**  
All CRM tables (merge current clients + prospects migrations).

**Configuration**  
Code format, default segment, duplicate rules.

**Permissions**  
`clients.*`, `prospects.*`, `clients.view_credit`

**Public API**  
`ClientsApi` only.

**Dependencies**  
`core`, `users`, `permissions`, `media` (optional attachments), `activity-log` (optional).

---

### 7.2 `packages/shared/catalogue`

**Responsibilities**  
Sellable/purchasable items: categories, brands, units, price tiers, item sets. **Does not** track quantities (→ Inventory).

**Models**  
`Item`, `Category`, `Brand`, `Unit`, `ItemUnitPrice`, `ItemSetComponent`

**Services**  
`ItemsApiService`, `ItemSetService`, `ItemsDeleteService`, `ItemsListColumnService`

**Events**  
`ItemCreated`, `ItemUpdated`, `ItemDeleted`

**Interfaces**  
Implements `ItemsApi`

**Routes**  
`/app/items`, create/edit/show, list config

**Views**  
Catalogue blades.

**Livewire**  
`ItemsIndex`, `ItemsForm`, `ItemsShow`, `ItemsListConfig`

**Migrations**  
items, categories, brands, units, prices, sets.

**Configuration**  
SKU rules, cost visibility defaults.

**Permissions**  
`items.view/create/update/delete/configure_list/view_cost`

**Public API**  
`ItemsApi`.

**Dependencies**  
`core`, `permissions`.

---

### 7.3 `packages/shared/inventory`

**Responsibilities**  
On-hand stock: levels, movements, storage locations, transfers, adjustments that change quantity. Implements `StockApi`. **Does not** run physical count sessions (→ Inventory Count). **Does not** define products (→ Catalogue).

**Models**  
`StockLevel`, `StockMovement`, `StorageLocation`, `ItemStorageLocation`

**Services**  
`StockService`, `StockMovementService`, `StorageLocationService`

**Events**  
`StockMoved`, `StockAdjusted`, `StockTransferred`, `StockBelowMinimum`

**Interfaces**  
Implements `StockApi`  
`StockMover`

**Routes**  
`/app/stock`, movements, transfer, adjustment, lookup

**Views**  
Stock blades + Excel export views.

**Livewire**  
`StockIndex`, `StockAdjustment`, `StockTransfer`, `StockLookup`, `StockMovementsIndex`

**Migrations**  
stock_levels, stock_movements, storage_locations, pivots.

**Configuration**  
Negative stock policy, default location.

**Permissions**  
`stock.view`, `stock.adjust`, `stock.transfer`

**Public API**  
`StockApi` (reserve, commit, release, getLevel).

**Dependencies**  
`core`, `catalogue` (via `ItemsApi` only).

---

### 7.4 `packages/shared/inventory-count`

**Responsibilities**  
Physical inventory sessions (counts) and count-driven adjustments. Separated from perpetual inventory to avoid mixing “live stock engine” with “stocktake process”.

**Models**  
`StockCount`, `StockCountLine`, `CountReason` / `Adjustment`, `Reason`

**Services**  
`InventoryCountService`

**Events**  
`StockCountCompleted` → listener calls `StockApi`

**Interfaces**  
None exported beyond events.

**Routes**  
`/app/inventory`

**Livewire**  
`InventoryIndex`, `InventoryForm`

**Migrations**  
stock_counts, lines, reasons.

**Permissions**  
`inventory.view`, `inventory.count`, `inventory.validate`

**Public API**  
Events only.

**Dependencies**  
`core`, `inventory` (`StockApi`), `catalogue` (`ItemsApi`).

---

### 7.5 `packages/shared/suppliers`

**Responsibilities**  
Supplier master data and payment terms. **Does not** create POs.

**Models**  
`Provider` / `Supplier`, `ProviderContact`, `PaymentTerm`

**Services**  
`SuppliersApiService`

**Events**  
`SupplierCreated`, `SupplierUpdated`

**Interfaces**  
`SuppliersApi`

**Routes**  
`/app/providers`

**Livewire**  
`ProvidersIndex`, `ProvidersForm`, `ProvidersShow`

**Migrations**  
providers, contacts, payment_terms.

**Permissions**  
`providers.*`

**Public API**  
`SuppliersApi`.

**Dependencies**  
`core`, `permissions`.

---

### 7.6 `packages/shared/purchases`

**Responsibilities**  
Purchase orders, receipts, purchase price history, foreign-currency purchases. Posts stock via `StockApi`.

**Models**  
`PurchaseOrder`, `PurchaseLine`, `ReceiptNote`, `ReceiptLine`, `ItemPurchasePrice`, foreign variants

**Services**  
`PurchasesService`, `ForeignPurchasesService`, `PurchaseDocumentNumberService`, `PurchasePriceHistoryService`

**Events**  
`PurchaseOrdered`, `PurchaseReceived`, `ForeignPurchaseReceived`

**Interfaces**  
Implements `PurchasingApi` (retail)

**Routes**  
`/app/purchases`, foreign purchases, receipts, print

**Livewire**  
Index/Form/Show + Receipt + Foreign* set

**Controllers**  
Print controllers (use Printing package)

**Migrations**  
All purchase/receipt tables.

**Permissions**  
`purchases.*`, `foreign_purchases.*`

**Public API**  
`PurchasingApi`.

**Dependencies**  
`core`, `catalogue`, `suppliers`, `inventory`, `printing`, `permissions`.

---

### 7.7 `packages/shared/sales`

**Responsibilities**  
POS/retail sales documents: cart checkout, sale lines, suspended sales, sale returns. **Does not** open/close till sessions (→ Cash Register). **Does not** manage invoices/devis (→ Invoicing / Quotations).

**Models**  
`Sale`, `SaleLine`, `Payment` (sale tender), `SuspendedSale`, `SaleReturn`, `SaleReturnLine`, `SaleReturnRefund`

**Services**  
`SalesApiService`, `SaleReturnsService`, `CheckoutService`

**Events**  
`SaleCompleted`, `SaleSuspended`, `SaleReturned`

**Interfaces**  
`SalesApi`

**Routes**  
`/app/sales`, returns, print ticket/invoice

**Livewire**  
`SalesForm` (POS), `SalesIndex`, return components

**Controllers**  
`SalePrintController`

**Migrations**  
sales, lines, payments, suspended, returns.

**Permissions**  
`sales.view/create`, `sales.modify_price`, `sales.return`

**Public API**  
`SalesApi`; events for stock/caisse listeners.

**Dependencies**  
`core`, `catalogue`, `crm`, `inventory`, `batches` (optional via `BatchesApi`), `cash-register` (via events/interface), `printing`, `permissions`.

---

### 7.8 `packages/shared/cash-register`

**Responsibilities**  
Till sessions, cash in/out ledger, session reports. **Does not** create sales.

**Models**  
`CaisseSession`, `CaisseEntry`

**Services**  
`CaisseService`, `CaisseReportService`, `CashLedger`

**Events**  
`CaisseOpened`, `CaisseClosed`, `CaisseEntryRecorded`

**Interfaces**  
`CashRegisterApi`

**Routes**  
`/app/caisse`

**Livewire**  
`CaisseIndex`

**Migrations**  
caisse_sessions, caisse_entries.

**Permissions**  
`caisse.open`, `caisse.close`, `caisse.view`

**Public API**  
`CashRegisterApi`.

**Dependencies**  
`core`, `permissions`, `stores` (optional), `printing`/`reports` for exports.

---

### 7.9 `packages/shared/quotations`

**Responsibilities**  
Retail/commercial quotes (ERP lineage): lines, taxes, acceptance workflow toward invoicing. **Not** BAT `devis` versioning/project spawn (vertical).

**Models**  
`Quotation`, `QuotationLine`, `QuotationTaxLine`

**Services**  
`QuotationsService`

**Events**  
`QuotationAccepted`, `QuotationRejected`, `QuotationSent`

**Interfaces**  
`QuotationsApi`

**Routes**  
`/app/quotations`, print

**Livewire**  
`QuotationsIndex`, `QuotationForm`

**Migrations**  
quotations family.

**Permissions**  
`quotations.*`

**Public API**  
`QuotationsApi` + events.

**Dependencies**  
`core`, `crm`, `catalogue`, `printing`, `permissions`.

---

### 7.10 `packages/shared/invoicing`

**Responsibilities**  
Customer invoices, sequences, delivery notes, collection reminders (ERP lineage). **Does not** record payment allocation (→ Payments). **Not** BAT facturation.

**Models**  
`Invoice`, `InvoiceLine`, `InvoiceTaxLine`, `InvoiceSequence`, `InvoiceSchedule`, `DeliveryNote`, `DeliveryNoteLine`

**Services**  
`InvoicingService`, `DeliveryNotesService`, `InvoiceScheduleService`, `CollectionReminderService`

**Events**  
`InvoiceIssued`, `InvoiceCancelled`, `DeliveryNoteCreated`, `InvoiceOverdue`

**Interfaces**  
Implements `InvoicingApi` (retail)

**Routes**  
`/app/invoices`, delivery notes, collection reminders, print

**Livewire**  
Invoice + DN + reminder components

**Migrations**  
Full invoicing set.

**Permissions**  
`invoicing.*`

**Public API**  
`InvoicingApi`.

**Dependencies**  
`core`, `crm`, `catalogue`, `quotations` (optional), `printing`, `permissions`.

---

### 7.11 `packages/shared/payments`

**Responsibilities**  
Allocation of money to invoices (and optionally unified payment intents). **Does not** define invoice documents. **Does not** own SaaS billing.

**Models**  
`InvoicePayment` (and future `PaymentAllocation`)

**Services**  
`InvoicePaymentsService`, `PaymentMethodCatalog` (cash, OM, MTN, transfer, …)

**Events**  
`InvoicePaymentRecorded`, `InvoiceFullyPaid`

**Interfaces**  
`RecordsInvoicePayment`

**Routes**  
`/app/invoice-payments`, print receipt

**Livewire**  
`InvoicePaymentsIndex`, `InvoicePaymentForm`

**Migrations**  
invoice_payments.

**Permissions**  
`invoice_payments.*`

**Public API**  
`RecordsInvoicePayment`.

**Dependencies**  
`core`, `invoicing` (via interface/ID), `printing`, `permissions`.

---

### 7.12 `packages/shared/debts`

**Responsibilities**  
Client debt schedules and repayments outside formal invoicing (shop credit books).

**Models**  
`Debt`, `DebtPayment`, `DebtSchedule`

**Services**  
`DebtsService`

**Events**  
`DebtOpened`, `DebtRepaymentRecorded`, `DebtSettled`

**Interfaces**  
`DebtsApi`

**Routes**  
`/app/debts`

**Livewire**  
`DebtsIndex`, `DebtForm`, `PaymentForm`

**Migrations**  
debts family.

**Permissions**  
`debts.*`

**Public API**  
`DebtsApi`.

**Dependencies**  
`core`, `crm`, `permissions`.

---

### 7.13 `packages/shared/expenses`

**Responsibilities**  
Operating expenses, categories, approval workflow.

**Models**  
`Expense`, `ExpenseCategory`, `Approval`

**Services**  
`ExpensesService`

**Events**  
`ExpenseSubmitted`, `ExpenseApproved`, `ExpenseRejected`

**Interfaces**  
None required beyond events.

**Routes**  
`/app/expenses`

**Livewire**  
`ExpensesIndex`, `ExpensesForm`

**Migrations**  
expenses family.

**Permissions**  
`expenses.*`, `expenses.approve`

**Public API**  
Events + query services for Reports.

**Dependencies**  
`core`, `permissions`, `users`.

---

### 7.14 `packages/shared/losses`

**Responsibilities**  
Loss records and reasons; impacts stock through `StockApi`.

**Models**  
`LossRecord`, `LossReason`

**Services**  
`LossesService`

**Events**  
`LossRecorded`

**Interfaces**  
None.

**Routes**  
`/app/losses`

**Livewire**  
`LossesIndex`, `LossesForm`

**Migrations**  
losses tables.

**Permissions**  
`losses.*`

**Public API**  
Events.

**Dependencies**  
`core`, `catalogue`, `inventory`, `permissions`.

---

### 7.15 `packages/shared/returns`

**Responsibilities**  
Customer return requests, approvals, credit notes, refunds, customer credit ledger, exchanges.

**Models**  
ReturnRequest, ReturnItem, ReturnReason, histories/logs, CreditNote(+Item), CustomerCredit, Refund (+ enums)

**Services**  
ReturnService, ReturnStockService, CreditNoteService, CustomerCreditService, RefundService, ReturnNumberGenerator, AuditLogger

**Events**  
`ReturnApproved`, `CreditNoteIssued`, `RefundCompleted`

**Interfaces**  
`ReturnsApi`

**Routes**  
`/app/returns`, credit notes, refunds, customer credit

**Livewire**  
Full returns suite

**Migrations**  
12-table family.

**Permissions**  
`returns.*`

**Public API**  
`ReturnsApi`.

**Dependencies**  
`core`, `crm`, `catalogue`, `inventory`, `invoicing`, `permissions`, `workflow` (status).

---

### 7.16 `packages/shared/reservations`

**Responsibilities**  
Hold stock for a client for later pickup/sale.

**Models**  
`Reservation`, `ReservationLine`

**Services**  
`ReservationService`

**Events**  
`ReservationCreated`, `ReservationFulfilled`, `ReservationCancelled`

**Interfaces**  
`ReservationsApi`

**Routes**  
`/app/reservations`

**Livewire**  
Index, Form, Show

**Migrations**  
reservations tables.

**Permissions**  
`reservations.*`

**Public API**  
`ReservationsApi`.

**Dependencies**  
`core`, `crm`, `catalogue`, `inventory`, `permissions`.

---

### 7.17 `packages/shared/reports`

**Responsibilities**  
Read-model analytics and exports (sales, margin, expenses, losses, top products). **No** writes to domain tables.

**Models**  
None (queries other modules via Interfaces / read DB).

**Services**  
`ReportingService`, exporters

**Events**  
None.

**Interfaces**  
`ReportDatasource` (optional adapters per module)

**Routes**  
`/app/reporting`

**Livewire**  
`ReportingIndex`

**Migrations**  
None (or materialized report tables later).

**Permissions**  
`reporting.view`, `reporting.export`

**Public API**  
None (UI + export).

**Dependencies**  
`core`, Interfaces of sales/expenses/losses/inventory — **only read APIs**.

---

### 7.18 `packages/shared/hr`

**Responsibilities**  
Employees, payroll runs, payslips, leave balances/requests. **Does not** track punches (→ Attendance).

**Models**  
Employee, salary history, adjustments, PayrollRun/Line/LineItem, LeaveType/Request/Balance

**Services**  
EmployeeService, PayrollService, PayrollCalculationService, LeaveService, PayrollAdjustmentService

**Events**  
`PayrollRunPosted`, `LeaveRequested`, `LeaveApproved`

**Interfaces**  
`EmployeeDirectory`

**Routes**  
`/app/payroll` (or `/app/hr`), payslip PDF

**Livewire**  
Employees + Leaves + PayrollRuns

**Migrations**  
HR/payroll tables.

**Permissions**  
`payroll.*`, `hr.employees.*`, `hr.leave.*`

**Public API**  
`EmployeeDirectory` (for Attendance, Tickets).

**Dependencies**  
`core`, `users`, `permissions`, `printing`.

---

### 7.19 `packages/shared/attendance`

**Responsibilities**  
Time punches and attendance sheets only.

**Models**  
`AttendancePunch`

**Services**  
`AttendanceService`

**Events**  
`AttendancePunched`

**Interfaces**  
None.

**Routes**  
`/app/attendance`, print sheets

**Livewire**  
`AttendanceIndex`, `AttendancePunchWidget`, `AttendanceSheet`

**Migrations**  
attendance_punches.

**Permissions**  
`attendance.view`, `attendance.punch`, `attendance.manage`

**Public API**  
Events.

**Dependencies**  
`core`, `hr` (`EmployeeDirectory`), `users`, `printing`.

---

### 7.20 `packages/shared/tickets`

**Responsibilities**  
Internal issue tracking / helpdesk inside a tenant.

**Models**  
`Ticket`, `TicketComment`

**Services**  
`TicketsService`

**Events**  
`TicketOpened`, `TicketResolved`

**Interfaces**  
None.

**Routes**  
`/app/tickets`

**Livewire**  
Index, Form, Show

**Migrations**  
tickets tables.

**Permissions**  
`tickets.*`

**Public API**  
None.

**Dependencies**  
`core`, `users`, `permissions`, `notifications` (optional).

---

### 7.21 `packages/shared/batches`

**Responsibilities**  
Lot/expiry tracking for pharmacy (and similar). Optional module.

**Models**  
`Batch`, `BatchMovement`

**Services**  
`BatchesApiService`

**Events**  
`BatchCreated`, `BatchExpired`

**Interfaces**  
Implements `BatchesApi`

**Routes**  
`/app/batches`

**Livewire**  
`BatchesIndex`, `BatchForm`

**Migrations**  
batches + sale_lines FK additives.

**Permissions**  
`batches.*`

**Public API**  
`BatchesApi`.

**Dependencies**  
`core`, `catalogue`.

---

### 7.22 `packages/shared/prescriptions`

**Responsibilities**  
Prescription capture and dispensing link to sales. Pharmacy only.

**Models**  
`Prescription`, `PrescriptionLine`

**Services**  
`PrescriptionService`

**Events**  
`PrescriptionDispensed`

**Interfaces**  
`PrescriptionsApi`

**Routes**  
`/app/prescriptions`

**Livewire**  
Index, Form

**Migrations**  
prescriptions + sales FK.

**Permissions**  
`prescriptions.*`

**Public API**  
`PrescriptionsApi`.

**Dependencies**  
`core`, `catalogue`, `crm`, `sales` (via interface/events).

---

## 8. Vertical Packages (detailed)

---

### 8.1 `packages/verticals/pressing`

**Responsibilities**  
Entire laundry bounded context: agences, pressing clients, article types/prices, orders, tri/constitution, Kanban workflow stages, fin production, deliveries, consumable issues, loyalty, pressing settings/reports. May **use** Catalogue/Inventory/CRM/Notifications — never modify their internals.

**Models**  
Agence, PressingClient, ArticleType, ArticlePrice, WorkflowStage, PressingOrder(+Item/Piece/ConstitutionLine), OrderStageHistory, PressingPayment, PressingDelivery, ConsumableIssue(+Line), LoyaltyEntry/Reward, PressingNotification(+Log)

**Services**  
Settlement, Sorting, Consumables, Loyalty, Notification orchestration (calls Notifications package), Dashboard, Billing modes support

**Events**  
`PressingOrderCreated`, `PressingOrderStageChanged`, `PressingOrderReady`, `PressingOrderDelivered`, `PressingPaymentReceived`

**Interfaces**  
None required globally; may publish `PressingOrderReader` for reports.

**Routes**  
All `/app/pressing-*` + public QR track route

**Livewire**  
~25 components (orders, kanban, settings hub, …)

**Migrations**  
Pressing-tagged migrations (~17)

**Configuration**  
`config/bproo/pressing.php` — stages, billing modes, loyalty defaults

**Permissions**  
`agences.*`, `pressing_orders.*`, `pressing_workflow.*`, … + role matrix profiles

**Public API**  
QR tracking; events for notifications

**Dependencies**  
`core`, `users`, `permissions`, `catalogue`, `inventory`, `notifications`, `printing`, `workflow`, `settings`

---

### 8.2 BAT verticals (one package each — never merge into retail)

#### `bat-offers`
Intake Kanban (projet/maintenance/service). Models: `Offer`. Uses CRM + Workflow.  
**Deps:** `core`, `crm`, `users`, `workflow`, `activity-log`

#### `bat-quotes`
Versioned devis, acceptance → project/invoice hooks via events.  
**Deps:** `core`, `crm`, `bat-offers`, `catalogue` (optional ItemsApi), `workflow`, `printing`, `media`

#### `bat-projects`
Chantiers & prestations: phases, tasks, members, progress.  
**Deps:** `core`, `crm`, `bat-quotes`, `users`, `workflow`

#### `bat-maintenance`
SLA contracts, orders, interventions.  
**Deps:** `core`, `crm`, `users`, `workflow`, `notifications`

#### `bat-planning`
Appointments & calendar.  
**Deps:** `core`, `crm`, `users`

#### `bat-site-tracking`
Site reports, task board, PV.  
**Deps:** `core`, `bat-projects`, `media`, `users`

#### `bat-logistics`
Deliveries, vehicles, drivers; emits `DeliveryCompleted` → stock listener.  
**Deps:** `core`, `inventory` (or BAT stock adapter), `workflow`

#### `bat-purchasing`
Supplier POs for projects (current `achats`). Implements `PurchasingApi` if desired.  
**Deps:** `core`, `bat-projects`, `crm`

#### `bat-invoicing`
Construction invoices & payments (current `facturation`). Implements `InvoicingApi`.  
**Deps:** `core`, `crm`, `bat-quotes`, `bat-projects`, `printing`, `payments` (optional shared payment methods catalog)

**Shared BAT note:** Prefer shared `crm`, `catalogue`, `hr`, `media`, `workflow`, `permissions` from platform/shared. Keep financial documents vertical until schemas converge.

---

## 9. Host Applications (composition roots)

| App | Enabled package sets |
|---|---|
| **apps/erp** | All platform + shared retail modules; pharmacy modules by tenant type; Stores |
| **apps/pressing** | Platform + CRM/Catalogue/Inventory subset + **pressing** vertical; retail modules optional |
| **apps/bat** | Platform (BillingDriver simple) + CRM + Media + Workflow + HR + **all bat-*** verticals |

Each app owns only:

- `config/modules.php` (enablement graph)
- `config/tenant_types.php`
- Theme / CSS
- `.env` / deploy
- Thin `routes/web.php` shell

---

## 10. Dependency Graph (simplified)

```
core
 ├─ tenancy ─ modules ─ admin
 │              └─ billing
 ├─ users ─ permissions
 ├─ settings
 ├─ auth
 ├─ audit / activity-log / workflow / notifications / media / printing / stores
 └─ (interfaces consumed by shared)

catalogue ← inventory ← inventory-count
         ← purchases ← suppliers
         ← sales ← cash-register
         ← batches ← prescriptions
crm ← sales / quotations / invoicing / payments / debts / reservations / returns
hr ← attendance
reports → (read APIs)

pressing → catalogue, inventory, notifications, workflow
bat-* → crm, workflow, media, (inventory adapter)
```

---

## 11. SOLID Mapping

| Principle | How this architecture enforces it |
|---|---|
| **S** | Cash Register ≠ Sales; Payments ≠ Invoicing; Permissions ≠ Users; Inventory ≠ Catalogue; Audit ≠ Activity Log |
| **O** | New verticals add packages; core Interfaces stay stable |
| **L** | Any `InvoicingApi` / `StockApi` implementation substitutable in Reports/POS |
| **I** | Small Interfaces (`ItemsApi`, `ClientsApi`, `StockApi`) instead of god facades |
| **D** | Sales depends on `StockApi`, not `StockLevel` model |

---

## 12. Mapping from Current Packages → Target

| Current | Target |
|---|---|
| `inovcom/kernel` | `platform/core` + parts of `workflow`, `activity-log` |
| Host `app/` tenancy/admin | `tenancy`, `modules`, `admin`, `billing` |
| `inovcom/users` | `users` + `permissions` (split) |
| `branding` + `configuration` | `settings` |
| `clients` + `prospects` | `crm` |
| `items` | `catalogue` |
| `stock` | `inventory` |
| `inventory` | `inventory-count` |
| `providers` | `suppliers` |
| `sales` | `sales` |
| `caisse` | `cash-register` |
| `invoice_payments` | `payments` |
| `payroll` | `hr` |
| `dms` | `media` |
| `kamfo/pressing` | `verticals/pressing` |
| `offres`…`logistique` | `verticals/bat-*` |
| `achats` / `facturation` | `bat-purchasing` / `bat-invoicing` (not merged with retail yet) |

---

## 13. Public API Stability Rules

1. **Never** break Interface method signatures without a major version.  
2. Module route names (`tenant.*.index`) stay stable for bookmarks and module registry.  
3. Permission key strings are a public contract.  
4. Migration tags stay stable for tenant installs.  
5. Eloquent models are **internal** unless explicitly exported.

---

## 14. What Was Deliberately Excluded

| Temptation | Why excluded |
|---|---|
| Single `accounting` mega-package | Would mix expenses, debts, invoicing, SaaS billing |
| Single `companies` package | Landlord Tenant ≠ CRM Client ≠ Store ≠ Agence |
| Merging BAT + ERP invoicing now | Schemas diverge; Interfaces first |
| Policies package | AuthZ is permission-key based; Policies optional later per entity |
| Repository layer package | Not used today; Services + Interfaces suffice |

---

## 15. Success Criteria

- Each package can be enabled/disabled per tenant without code edits in other packages.  
- Pressing and BAT can upgrade shared `crm`/`catalogue` independently of each other.  
- No shared package imports a `Verticals\*` class.  
- Host `app/Models` contains **zero** business domain models.  
- Cross-package communication measurable: only Interfaces + Events.

---

*End of ideal modular architecture document. Companion docs: `ARCHITECTURE_AUDIT.md`, `DUPLICATION_REPORT.md`.*
