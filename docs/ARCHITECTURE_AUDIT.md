# Bproo Platform — Architecture Audit

**Date:** 2026-07-28  
**Scope:** Read-only analysis of `bproo-erp`, `bproo-pressing`, `bproo-bat`  
**Goal:** Identify existing business modules, duplication, reusable Laravel packages, and a safe migration path to a modular monorepo — **without rewriting** and **without breaking existing customers**.  
**Constraint:** No code was modified. This document is analysis only.

---

## Executive Summary

You already have a **modular multi-tenant Laravel architecture**, not three unrelated monoliths. Each product is a Laravel 10 + Livewire 4 host app (`app/` = SaaS control plane) plus local Composer path packages under `packages/inovcom/*`.

| Product | Path | Vertical focus | Domain packages |
|---|---|---|---|
| **ERP + POS** | `bproo-erp/` | Retail / pharmacy / bakery / restaurant | 27 `inovcom/*` packages |
| **Pressing** | `bproo-pressing/` | Dry-cleaning / laundry | Same ~27 `inovcom/*` packages **+** `kamfo/pressing` |
| **Construction (BAT)** | `bproo-bat/bproo-bat/` | Chantier / renovation / maintenance | 17 `inovcom/*` packages (different set) |

**Critical finding:** ERP and Pressing are **near-clones** of the same Inov-Com platform (same tenancy, billing, admin, and almost identical `inovcom/*` packages). Pressing adds one vertical package. BAT shares the *same architectural pattern* and some package *names*, but several “shared” packages (`clients`, `stock`, `items`, `kernel`) are **divergent forks**, not the same code.

**Do not rewrite.** Consolidate by:

1. Extracting a **shared platform kernel** (already ~90% duplicated between ERP and Pressing).
2. Deduplicating identical `inovcom/*` packages into a single monorepo package registry.
3. Keeping vertical packages (`pressing`, BAT chantier modules) as product-specific add-ons.
4. Treating BAT’s similarly named packages as **version forks** that need a careful merge or coexistence strategy.

---

## 1. Current Landscape

### 1.1 Common platform pattern (all three apps)

```
┌─────────────────────────────────────────────────────────────┐
│  Host Laravel app (app/)                                     │
│  • Admin portal /admin   (super-admins, tenants, modules)    │
│  • Tenant shell /app     (login, dashboard, subscription)    │
│  • Tenancy, module registry, provisioning jobs               │
└───────────────────────────┬─────────────────────────────────┘
                            │ Composer path repos
┌───────────────────────────▼─────────────────────────────────┐
│  packages/inovcom/*                                          │
│  • kernel (contracts, TenantModel)                           │
│  • users (RBAC)                                              │
│  • domain modules (Livewire + migrations + ModuleLifecycle)│
└─────────────────────────────────────────────────────────────┘
```

| Concern | Implementation |
|---|---|
| Multi-tenancy | Database-per-tenant (PostgreSQL), dynamic `tenant` connection |
| Tenant resolution | Query `?tenant=`, session, planned subdomain/header |
| Guards | `web` (system admin) vs `tenant` (company users) |
| Modules | `config/modules.php` + `ModuleRegistry` install/uninstall |
| AuthZ | Custom RBAC (`roles` / `permissions` pivots) — **not Spatie** |
| Policies | **None** — Livewire concerns + `hasPermission()` |
| Repositories | **None** — Eloquent in Services / Livewire |
| Frontend | Livewire 4 + Blade (ERP/Pressing: custom CSS; BAT: Tailwind) |
| PDF | `barryvdh/laravel-dompdf` |

### 1.2 Admin (present in all apps)

Admin is **not a separate package**. It lives in each host `app/`:

- Controllers: `Admin\AuthController`, `Admin\ModuleEventsExportController`
- Livewire: Tenants, TenantForm, TenantModules, TenantHealth, TenantSettings, ModuleMarketplace / Modules, ModuleEvents, (+ Plans/Subscription in ERP & Pressing, Analytics in BAT)
- Models: `Tenant`, `Module`, `TenantModule`, `ModuleEvent`, `TenantSetting`, `User` (system)
- Extra in ERP/Pressing only: `Plan`, `Subscription`, `TenantPayment`, `TenantBalanceTransaction`

### 1.3 POS (inside ERP)

POS is **not a separate app**. It is the `inovcom/sales` package:

- Primary UI: `SalesForm` Livewire (cart, multi-payment, park sale)
- Adjacent: `caisse`, `stock`, `batches`, `prescriptions`
- Payments: cash, Orange Money, MTN Money, credit

---

## 2. Cross-Cutting Architectural Conventions

These apply across packages and should be preserved in the monorepo:

| Convention | Detail |
|---|---|
| Package layout | `{Module}ServiceProvider`, `{Module}Module` (lifecycle), `Models/`, `Http/Livewire/`, `Services/`, `database/migrations/`, `resources/views/` |
| Routes | Registered **in the ServiceProvider** (no per-package `routes/*.php` files in most packages) |
| Boot guard | `LazyModuleBoot` / `shouldBootModule()` |
| Base model | `InovCom\Kernel\TenantModel` (`$connection = 'tenant'`) |
| Permissions | Seeded in `{Module}::install()` via `defaultPermissions()`, not Laravel seeders |
| Middleware gate | `module:{key}` → enabled for tenant **and** user permission |
| Cross-module APIs | Kernel contracts: `ItemsApi`, `ClientsApi`, (`BatchesApi` in ERP/Pressing) |
| Events | Mostly landlord `App\Events\*`; packages rarely own Events/Policies/Repositories/Helpers |

**Empty by design across nearly all packages:**

- Policies → `[]`
- Repositories → none
- Package-local Helpers → rare
- Package-local Events → rare (BAT `logistique` and workflow exceptions)

---

## 3. Application A — ERP + POS (`bproo-erp`)

### 3.1 Host app modules (control plane)

| Module | Purpose | Key artifacts |
|---|---|---|
| **Authentication (Admin)** | Super-admin login | `Admin\AuthController`, guard `web` |
| **Authentication (Tenant)** | Company user login | `Tenant\AuthController`, guard `tenant` |
| **Tenancy** | Resolve DB, provision tenants | `TenantManager`, `TenantProvisioner`, `ProvisionTenantJob`, middleware `SetTenantConnection` |
| **Subscriptions / Billing** | SaaS plans, balance, suspension | `Plan`, `Subscription`, `TenantPayment`, `TenantBalanceTransaction`, `SubscriptionService` |
| **Module Marketplace** | Enable/disable packages per tenant | `ModuleRegistry`, `ModuleMarketplace`, `InstallModuleJob` |
| **Admin Dashboard** | Platform ops | `Livewire\Admin\*` |
| **Tenant Dashboard** | In-app home + notifications | `Livewire\Tenant\Dashboard`, `NotificationBell`, `SubscriptionStatus` |
| **Multi-store** | Stores inside one tenant | `StoreContextService`, `MultiStoreSetupService`, `SetCurrentStore` |
| **Print / Documents** | Shared PDF helpers | `PrintDocument`, `DocumentTaxCalculator`, `CashLedger`, etc. |

**Dependencies:** All domain packages; `InovCom\Kernel\Contracts\ModuleLifecycle`.  
**Migrations (central):** ~24 — `tenants`, `modules`, `tenant_modules`, `plans`, `subscriptions`, jobs, system `users`.  
**Events:** `ItemCreated/Updated/Deleted`, `ClientCreated/Updated` (central).  
**Policies / Repositories / Helpers:** Policies empty; helpers in `app/helpers.php` + `app/Support/*`.

### 3.2 Domain packages (`packages/inovcom/*`)

#### Platform foundation

##### `kernel`
- **Purpose:** Contracts and tenant Eloquent base; no business UI.
- **Dependencies:** Illuminate only.
- **Models:** abstract `TenantModel`.
- **Contracts:** `ModuleLifecycle`, `ItemsApi`, `ClientsApi`, `BatchesApi`.
- **Traits:** `LazyModuleBoot`. **Casts:** `TrimmedDecimal`.
- **Controllers / Livewire / Services / Routes / Migrations / Events / Policies / Permissions / Helpers / Repositories:** none (by design).

##### `users`
- **Purpose:** Tenant users, roles, permissions (RBAC).
- **Dependencies:** `kernel`.
- **Models:** `User`, `Role`, `Permission`.
- **Livewire:** `UsersIndex`, `UserForm`, `RolesIndex`, `RoleForm`, `PermissionsMatrix`.
- **Services/Support:** `PermissionCatalog`.
- **Migrations:** 4 — `roles`, `permissions`, `role_user`, `permission_role`.
- **Permissions:** ~35 defaults + `admin` / `cashier` roles on install.
- **Routes:** `/app/users`, `/app/roles`, `/app/permissions`.
- **Controllers / Events / Policies / Repositories:** none.

##### `branding`
- **Purpose:** Shop name / welcome message (tenant settings).
- **Dependencies:** framework/Livewire (weak kernel coupling).
- **Livewire:** `BrandingIndex`.
- **Models / Migrations:** none (uses `TenantSetting`).
- **Note:** Present on disk; wiring into root `composer.json` / providers should be verified (possible dormant registration).

##### `configuration`
- **Purpose:** Tenant system settings UI.
- **Dependencies:** `kernel`, `users`.
- **Livewire:** `ConfigurationIndex`.
- **Models / Migrations:** none (settings-backed).

#### Commercial / CRM

##### `clients`
- **Purpose:** Customer master data, credit, 360° workspace.
- **Dependencies:** `kernel`.
- **Models (10):** `Client`, `Segment`, `Address`, `Contact`, `CreditLimit`, `Zone`, `ClientCategory`, `ClientNote`, `ClientDocument`, `ClientReminder`.
- **Services (7):** `ClientsApiService`, aging/credit/debt insight/duplicates/code generator/product history.
- **Livewire (5):** Index, Form, Show, Client360, Duplicates.
- **Migrations:** 16.
- **Exports:** Clients + product history.
- **Permissions:** `clients.*` via lifecycle.
- **Events (host):** ClientCreated/Updated.

##### `providers`
- **Purpose:** Supplier management.
- **Dependencies:** `kernel`.
- **Models:** `Provider`, `PaymentTerm`, `ProviderContact`.
- **Services:** `ProvidersApiService`.
- **Livewire:** Index, Form, Show.
- **Migrations:** 4.

##### `prospects`
- **Purpose:** Lead tracking → convert to client.
- **Dependencies:** `kernel`, `clients`, `users`.
- **Models:** `Prospect`, `ProspectActivity`.
- **Services:** `ProspectsService`.
- **Livewire:** Index, Form, Show.
- **Migrations:** 1 (multi-table).

##### `quotations`
- **Purpose:** Client quotes (devis) with validation workflow.
- **Dependencies:** `clients`, `items`, `kernel`.
- **Models:** `Quotation`, `QuotationLine`, `QuotationTaxLine`.
- **Services:** `QuotationsService`.
- **Livewire:** Index, Form. **Controller:** `QuotationPrintController`.
- **Migrations:** 11 (heavy churn).

##### `invoicing`
- **Purpose:** Invoices, delivery notes, collection reminders.
- **Dependencies:** `clients`, `items`, `kernel`, `quotations`.
- **Models (7):** `Invoice`, `InvoiceLine`, `InvoiceTaxLine`, `InvoiceSchedule`, `InvoiceSequence`, `DeliveryNote`, `DeliveryNoteLine`.
- **Services:** Invoicing, DeliveryNotes, InvoiceSchedule, CollectionReminder.
- **Livewire (6):** Invoices + Delivery notes + Collection reminders.
- **Controllers (4):** Print/PDF variants.
- **Migrations:** 18 (largest generic set).

##### `invoice_payments`
- **Purpose:** Payments against invoices.
- **Dependencies:** `invoicing`, `kernel`.
- **Models:** `InvoicePayment`.
- **Services:** `InvoicePaymentsService`.
- **Livewire:** Index, Form. **Controller:** Print.
- **Migrations:** 2.

##### `debts`
- **Purpose:** Client debts, schedules, repayments.
- **Dependencies:** `clients`, `kernel`.
- **Models:** `Debt`, `DebtPayment`, `DebtSchedule`.
- **Services:** `DebtsService`.
- **Livewire:** Index, DebtForm, PaymentForm.
- **Migrations:** 4.

##### `reservations`
- **Purpose:** Product holds with stock reservation.
- **Dependencies:** `clients`, `items`, `kernel`, `stock`.
- **Models:** `Reservation`, `ReservationLine`.
- **Services:** `ReservationService`.
- **Livewire:** Index, Form, Show.
- **Migrations:** 2.

##### `returns`
- **Purpose:** Returns, credit notes, refunds, exchanges (most enterprise-grade package).
- **Dependencies:** `clients`, `items`, `kernel`, `invoicing`, `stock`.
- **Models (12):** ReturnRequest/Item/Reason/StatusHistory/ApprovalLog/Comment/Attachment/AuditLog, CreditNote(+Item), CustomerCredit, Refund.
- **Services (7):** Return, ReturnStock, CreditNote, CustomerCredit, Refund, NumberGenerator, AuditLogger.
- **Enums (7):** statuses, types, conditions, refund methods.
- **Livewire (7):** Returns + Credit notes + Refunds + Customer credit ledger.
- **Migrations:** 12.

#### Catalogue / Inventory / Purchasing

##### `items`
- **Purpose:** Catalogue, categories, brands, units, sets, price tiers.
- **Dependencies:** `kernel`.
- **Models (6):** `Item`, `Category`, `Brand`, `Unit`, `ItemUnitPrice`, `ItemSetComponent`.
- **Services:** `ItemsApiService` (+ delete/set/list-column).
- **Livewire (5):** Index, Form, Show, ListConfig (+ authorize concern).
- **Migrations:** 8.
- **Permissions:** `items.view/create/update/delete/configure_list/view_cost`.

##### `stock`
- **Purpose:** Levels, movements, storage locations, transfers.
- **Dependencies:** `kernel`, `items`.
- **Models:** `StockLevel`, `StockMovement`, `StorageLocation`, `ItemStorageLocation`.
- **Services:** `StockService`, `StockMovementService`, `StorageLocationService`.
- **Livewire (5):** Index, Adjustment, Transfer, Lookup, Movements.
- **Migrations:** 4. **Export:** Excel.

##### `inventory`
- **Purpose:** Physical stock counts & adjustments.
- **Dependencies:** `kernel`.
- **Models:** `StockCount`, `StockCountLine`, `Adjustment`, `Reason`.
- **Services:** `InventoryService`.
- **Livewire:** Index, Form.
- **Migrations:** 4.

##### `purchases`
- **Purpose:** POs/receipts + foreign-currency purchases.
- **Dependencies:** `kernel`, `items`, `providers`, `stock`.
- **Models (10):** Purchase/Foreign purchase + lines + receipts + price history.
- **Services (4):** Purchases, ForeignPurchases, DocumentNumber, PriceHistory.
- **Livewire (8):** Domestic + foreign flows.
- **Controllers:** Print (×2).
- **Migrations:** 10.
- **Lifecycle:** two module keys — `purchases` and `foreign_purchases`.

##### `losses`
- **Purpose:** Loss records with stock impact.
- **Dependencies:** `items`, `kernel`, `stock`.
- **Models:** `LossRecord`, `LossReason`.
- **Services:** `LossesService`.
- **Livewire:** Index, Form.
- **Migrations:** 2.

#### POS / Cash / Sales

##### `sales` ← **POS module**
- **Purpose:** POS checkout, sales history, sale returns.
- **Dependencies:** `kernel`, `items`, `clients` (+ runtime stock/batches).
- **Models (7):** `Sale`, `SaleLine`, `Payment`, `SuspendedSale`, `SaleReturn`, `SaleReturnLine`, `SaleReturnRefund`.
- **Services:** `SalesApiService`, `SaleReturnsService`.
- **Livewire:** `SalesForm` (POS), Index, Return Form/Show/Index.
- **Controller:** `SalePrintController`.
- **Migrations:** 6.
- **Permissions:** includes `sales.modify_price`, create/view, etc.

##### `caisse`
- **Purpose:** Till sessions & cash ledger.
- **Dependencies:** `kernel`.
- **Models:** `CaisseSession`, `CaisseEntry`.
- **Services:** `CaisseService`, `CaisseReportService`.
- **Livewire:** `CaisseIndex`.
- **Migrations:** 3. **Export:** Excel.

#### Pharmacy vertical (ERP tenant types)

##### `batches`
- **Purpose:** Lot / expiry tracking.
- **Dependencies:** `kernel`, `items`.
- **Models:** `Batch`, `BatchMovement`.
- **Services:** `BatchesApiService` (`BatchesApi`).
- **Livewire:** Index, Form.
- **Migrations:** 3.
- **Tenant types:** pharmacy.

##### `prescriptions`
- **Purpose:** Prescriptions & dispensing linked to sales.
- **Dependencies:** `kernel`, `items`, `clients`.
- **Models:** `Prescription`, `PrescriptionLine`.
- **Livewire:** Index, Form.
- **Migrations:** 3.
- **Services:** none dedicated.

#### Finance / HR / Ops / Support

##### `expenses`
- **Purpose:** Expenses, categories, approval.
- **Dependencies:** `kernel`.
- **Models:** `Expense`, `ExpenseCategory`, `Approval`.
- **Services:** `ExpensesService`.
- **Livewire:** Index, Form.
- **Migrations:** 3.

##### `reporting`
- **Purpose:** Analytics (margin, expenses, losses, top products).
- **Dependencies:** `kernel` (reads other modules).
- **Models / Migrations:** none.
- **Services:** `ReportingService`. **Livewire:** `ReportingIndex`. **Export:** Excel.

##### `payroll`
- **Purpose:** Employees, payroll runs, leave.
- **Dependencies:** `kernel`, `users`.
- **Models (9):** Employee, salary history, adjustments, PayrollRun/Line/LineItem, LeaveType/Request/Balance.
- **Services (5):** Employee, Leave, Payroll, Calculation, Adjustment.
- **Livewire (6):** Employees + Leaves + Payroll runs.
- **Controller:** `PayslipPrintController`.
- **Migrations:** 10.

##### `attendance`
- **Purpose:** Time punches / sheets.
- **Dependencies:** `kernel`, `users`.
- **Models:** `AttendancePunch`.
- **Services:** `AttendanceService`.
- **Livewire:** Index, PunchWidget, Sheet.
- **Controllers:** Sheet print (×2).
- **Migrations:** 2.

##### `tickets`
- **Purpose:** Internal issue tracking.
- **Dependencies:** `kernel`, `users`.
- **Models:** `Ticket`, `TicketComment`.
- **Services:** `TicketsService`.
- **Livewire:** Index, Form, Show.
- **Migrations:** 1.

---

## 4. Application B — Pressing (`bproo-pressing`)

### 4.1 Relationship to ERP

Pressing is the **same Inov-Com host + same 27 inovcom packages**, with:

- Default tenant type = `pressing`
- Extra Composer package: `kamfo/pressing` (`packages/pressing`)
- Extra artisan: `PressingInstallTenant`, loyalty/consumables/role sync, demo seeders
- QR dependency: `endroid/qr-code`
- Agency scoping bolted onto users (`assigned_agence_id`)

**Package drift ERP ↔ Pressing (sampled):**

| Package | Identical PHP files | Differing | Verdict |
|---|---|---|---|
| kernel | 7 | 0 | **Identical** |
| items | 29 | 0 | **Identical** |
| sales | 30 | 0 | **Identical** |
| clients | 57 | 1 | Near-identical |
| users | 17 | 3 | Minor drift |
| stock | 21 | 5 | Small drift |
| invoicing | 39 | 16 | **Meaningful drift** |

Treat ERP/Pressing inovcom packages as **one lineage with small forks**, not separate products.

### 4.2 Host / Admin / Shared inovcom modules

Same inventory as §3 (Authentication, Tenancy, Subscriptions, Admin, Dashboard, Settings, and all `inovcom/*` modules). Many retail/pharmacy modules are **installed but optional** for pressing tenants.

### 4.3 Vertical package — `kamfo/pressing`

**Purpose:** Dry-cleaning operational stack: agencies, reception, orders, Kanban production, delivery, loyalty, consumables, notifications.

**Dependencies:** `inovcom/kernel`, and runtime use of `users`, `items`, `stock`.

**Module keys** (all `tenant_types => ['pressing']`):  
`agences`, `pressing_clients`, `pressing_orders`, `pressing_workflow`, `pressing_fin_production`, `pressing_deliveries`, `pressing_settings`, `pressing_reports`, `pressing_consumables`, `pressing_loyalty`.

| Artifact | Inventory |
|---|---|
| **Models (17+)** | `Agence`, `PressingClient`, `ArticleType`, `ArticlePrice`, `WorkflowStage`, `PressingOrder`, `PressingOrderItem`, `PressingOrderPiece`, `PressingOrderConstitutionLine`, `OrderStageHistory`, `PressingPayment`, `PressingDelivery`, `PressingConsumableIssue(+Line)`, `PressingLoyaltyEntry`, `PressingLoyaltyReward`, `PressingNotification`, `PressingNotificationLog` |
| **Controllers** | `PressingOrderPrintController`, `PressingOrderQrController` (public QR tracking) |
| **Livewire (~25)** | Agences, Clients, Orders (+ Tri), Kanban, Workflow stages, Fin production, Deliveries, Consumables, Loyalty, Reports, Settings hub (types/prices/delays/taxes/messages/payments/notifications/loyalty) |
| **Services** | Settlement, Sorting, Consumables, Loyalty, NotificationDispatcher, Dashboard |
| **Support** | Billing modes, Constitution, Workflow constants, AgenceContext, Profile, Settings, QR, RolePermissions |
| **Migrations** | 17 |
| **Events / Policies / Repositories** | none dedicated |
| **Permissions** | Per lifecycle module (`pressing_orders.view/create/sort/pay/...`) + role matrix (reception/production/repassage/driver) |
| **Helpers** | Support classes act as domain helpers |

**What makes Pressing unique vs ERP:** physical piece pipeline (tri → lavage → séchage → repassage), weight/fixed/mixed billing, delivery-gated credit, multi-channel WhatsApp/SMS notifications, loyalty, agences, customer QR tracking.

---

## 5. Application C — Construction / BAT (`bproo-bat/bproo-bat`)

### 5.1 Host differences vs ERP/Pressing

| Area | BAT | ERP/Pressing |
|---|---|---|
| Subscription billing models | Plan string on `Tenant` (simpler) | Full `Plan` / `Subscription` / payments / balance |
| CSS | Tailwind | Custom CSS / Inter |
| Kernel extras | `WorkflowStateMachine`, `Auditable`, `AuditLog`, `ServiceCatalog` | Leaner kernel (contracts + TenantModel) |
| Domain events | Richer (`QuoteAccepted`, `WorkflowTransitioned`, etc.) | Item/Client module events mainly |
| Permission seeding | Dual catalogues (`UsersModule` + `TenantPermissionSeeder`) | Module lifecycle defaults |

Admin still covers tenants, modules, marketplace stub, health, analytics.

### 5.2 Shared-name packages (same name, **different product**)

These exist in BAT **and** ERP/Pressing but are **not the same implementation**:

| Package | ERP/Pressing | BAT | Merge risk |
|---|---|---|---|
| `clients` | 10 models, 16 migrations, 360 workspace | 3 models (`Client`, `ClientContact`, `ClientActivity`), 1 migration | **High** |
| `stock` | Item-linked levels/locations | `Product` / `Warehouse` / movements | **High** |
| `items` | Rich catalogue + sets + tiers | Simpler catalogue; optional module | Medium |
| `users` | Same shape (3 models / 5 LW) | Same shape; different permission catalogue & roles | Medium |
| `kernel` | Contracts + TenantModel | + workflow/audit/ServiceCatalog | Medium |
| `branding` | Shop branding | Maps to `configuration` module key | Low |

### 5.3 BAT domain packages

#### Commercial cycle

##### `clients`
- **Purpose:** Client pivot of the BAT ERP.
- **Dependencies:** `kernel`.
- **Models:** `Client`, `ClientContact`, `ClientActivity`.
- **Services:** `ClientsApiService`.
- **Livewire:** Index, Form, ClientAccount, ClientAccountView.
- **Migrations:** base (+ landlord enhancements).
- **Permissions:** `clients.*`.

##### `offres`
- **Purpose:** Commercial intake (projet / maintenance / service).
- **Dependencies:** `kernel`, `clients`, `users`.
- **Models:** `Offer` (workflow + auditable).
- **Livewire:** Index, Form, Kanban.
- **Migrations:** offers + enhancements.

##### `devis`
- **Purpose:** Quotes with versioning; acceptance spawns project/invoice/contract.
- **Dependencies:** `kernel`, `clients`, `offres`.
- **Models:** `Quote`, `QuoteLine`.
- **Services:** Acceptance, CreateProject, CreateInvoice, Billing, Duplication, Import, Spreadsheet, Email draft/mailto, Source archive.
- **Controllers:** PDF, Excel, Email draft.
- **Livewire:** Index, Form, Show.
- **Events (host):** `QuoteAccepted`, `QuoteSent`.

##### `facturation`
- **Purpose:** Invoices + payments for construction cycle.
- **Dependencies:** `kernel`, `clients`, `devis`, `projets`.
- **Models:** `Invoice`, `InvoiceLine`, `InvoicePayment`.
- **Controllers:** Invoice PDF, Payment receipt PDF.
- **Livewire:** Index, Form.
- **Note:** Parallel concept to ERP `invoicing` + `invoice_payments`, different schema/API.

##### `achats`
- **Purpose:** Purchase orders & suppliers (project cost feed).
- **Dependencies:** `kernel`, `clients`, `projets`.
- **Models:** `Supplier`, `PurchaseOrder`, `PurchaseOrderLine`.
- **Livewire:** POs + Suppliers Index/Form.
- **Note:** Parallel to ERP `purchases` + `providers`, different model names.

#### Execution / field

##### `projets` (+ virtual `prestations`)
- **Purpose:** Chantiers — phases, tasks, members, progress/budget.
- **Dependencies:** `kernel`, `clients`, `offres`, `devis`, `users`.
- **Models:** `Project`, `ProjectPhase`, `ProjectTask`, `ProjectMember`.
- **Livewire:** ProjectsIndex, PrestationsIndex, ProjectForm.
- **Coupling:** `recalculateActualCost()` may reach into Achats via `class_exists`.

##### `maintenance`
- **Purpose:** SLA contracts, orders, interventions.
- **Dependencies:** `kernel`, `clients`, `users`.
- **Models:** `MaintenanceContract`, `MaintenanceOrder`, `Intervention`.
- **Services:** CreateContractFromQuote, MaintenanceCycle.
- **Livewire:** Contracts/Orders/Intervention forms.
- **Console:** preventive order generation, SLA breach checks.

##### `planning`
- **Purpose:** Appointments, site visits, milestones.
- **Dependencies:** `kernel`, `clients`, `users`.
- **Models:** `Appointment`, `AppointmentParticipant`.
- **Livewire:** Calendar, AppointmentForm.

##### `suivi`
- **Purpose:** Daily site reports, task board, client PV.
- **Dependencies:** `kernel`, `clients`, `users`.
- **Models:** `SiteReport`, `ProjectTask` (shared/extended with projets).
- **Livewire:** SiteReports Index/Form, TaskBoard.

##### `logistique`
- **Purpose:** Deliveries, vehicles, drivers; stock deduction on complete.
- **Dependencies:** `kernel`, `stock`.
- **Models:** `Delivery`, `DeliveryItem`, `Driver`, `Vehicle`.
- **Events:** `DeliveryCompleted` → `DeductStockFromDelivery`.
- **Services:** `LogisticsService`.
- **Livewire:** Deliveries + Drivers + Vehicles.

##### `dms`
- **Purpose:** Documents & polymorphic attachments.
- **Dependencies:** `kernel`, `users`.
- **Models:** `Document`, `DocumentAttachment`.
- **Services:** `StorageService`.
- **Livewire:** DocumentsIndex, DocumentUpload, EntityDocuments (embeddable).

#### Support modules

##### `rh`
- **Purpose:** Employees, payslips, leave (BAT HR).
- **Dependencies:** `kernel`, `users`.
- **Models:** `Employee`, `Payslip`, `Leave`.
- **Controller:** Payslip PDF.
- **Livewire:** Employees Index/Form/Show.
- **Note:** Parallel to ERP `payroll` (+ leave), simpler schema.

##### `stock` / `items` / `users` / `branding` / `kernel`
- Covered in §5.2 and package inventory above.

---

## 6. Duplication Matrix

### 6.1 Duplicated across ERP and Pressing (copy/near-copy)

Everything in the Inov-Com platform layer and all 27 `inovcom/*` packages — **highest consolidation ROI**.

Including host `app/` concerns: tenancy, admin, subscriptions, module registry, multi-store, print support, dashboard shell.

### 6.2 Conceptually duplicated across ERP/Pressing and BAT (same idea, different code)

| Business capability | ERP / Pressing package(s) | BAT package(s) | Recommendation |
|---|---|---|---|
| Platform kernel | `app/` + `kernel` | `app/` + richer `kernel` | Unify host; extend BAT kernel features into shared kernel carefully |
| Users / RBAC | `users` | `users` | Shared package + pluggable permission catalogues |
| Branding / settings | `branding`, `configuration` | `branding`→configuration | One `settings`/`branding` package |
| Clients / CRM | `clients` (+ prospects) | `clients` (+ offres) | **Do not force-merge schemas**; shared contract + adapters or versioned packages |
| Catalogue | `items` | `items` | Shared core + ERP extensions |
| Stock | `stock` (+ inventory) | `stock` (+ logistique) | Shared movement engine long-term; short-term keep forks |
| Purchasing | `purchases` + `providers` | `achats` | Rename/align later; keep separate initially |
| Quotations | `quotations` | `devis` | Separate vertical packages; extract shared tax/line helpers |
| Invoicing | `invoicing` + `invoice_payments` | `facturation` | Same |
| HR / Payroll | `payroll` + `attendance` | `rh` | Extract shared Employee core later |
| Admin | host `app/` | host `app/` | Shared `bproo/platform` package or app skeleton |

### 6.3 Unique to one application

| App | Unique modules |
|---|---|
| **ERP/POS** | POS sales depth, `caisse`, `batches`, `prescriptions`, multi-store retail patterns, foreign purchases, returns/reservations enterprise stack (also present in Pressing codebase but retail-oriented) |
| **Pressing** | Entire `kamfo/pressing` vertical (agences, orders, workflow, tri, deliveries, loyalty, consumables, QR, WhatsApp/SMS) |
| **BAT** | `offres`, `devis`, `projets`/`prestations`, `maintenance`, `planning`, `suivi`, `dms`, `logistique`, `achats`, `facturation`, workflow/audit kernel traits, SLA jobs |

---

## 7. What Should Become Reusable Laravel Packages

### Tier 0 — Shared platform (extract first)

| Proposed package | Source of truth | Contents |
|---|---|---|
| `bproo/platform` or keep host skeleton | ERP/Pressing `app/` (billing-complete) | Tenancy, ModuleRegistry, Admin Livewire, Jobs, Middleware, Subscriptions |
| `inovcom/kernel` | Merge ERP lean + BAT workflow/audit | TenantModel, contracts, LazyModuleBoot, optional Workflow/Auditable |
| `inovcom/users` | ERP/Pressing (near-identical) | RBAC + Livewire; permission catalogues injectable per vertical |
| `inovcom/branding` + `inovcom/configuration` | ERP/Pressing | Tenant settings UI |

### Tier 1 — Shared business packages (safe to share ERP ↔ Pressing immediately)

Because they are identical or near-identical today:

`items`, `clients`, `providers`, `sales`, `stock`, `purchases`, `inventory`, `expenses`, `losses`, `debts`, `caisse`, `reporting`, `payroll`, `attendance`, `batches`, `prescriptions`, `quotations`, `invoicing`, `invoice_payments`, `tickets`, `returns`, `reservations`, `prospects`

**Action:** One package copy in monorepo; both apps require it. Reconcile the few differing files (especially `invoicing`, `stock`, `users`) before cutover.

### Tier 2 — Vertical packages (never force into “core”)

| Package | Product |
|---|---|
| `kamfo/pressing` or `bproo/pressing` | Pressing only |
| `inovcom/offres`, `devis`, `projets`, `maintenance`, `planning`, `suivi`, `dms`, `logistique`, `achats`, `facturation`, `rh` | BAT only |
| Pharmacy modules (`batches`, `prescriptions`) | ERP (pharmacy tenants) |
| POS-centric (`sales`, `caisse`) | ERP + optional Pressing |

### Tier 3 — Long-term unification candidates (do later; high risk)

- Clients (ERP 360 vs BAT lean)
- Stock (item-centric vs product/warehouse)
- Invoicing vs Facturation
- Purchases vs Achats
- Payroll vs RH

These should share **contracts and print/tax utilities**, not a forced single schema in phase 1.

---

## 8. Target Modular Monorepo Shape

```
bproo-platform/
├── apps/
│   ├── erp/          # thin host: config, routes shell, assets
│   ├── pressing/         # thin host + pressing-enabled modules.php
│   └── bat/              # thin host + BAT modules.php
├── packages/
│   ├── platform/         # shared SaaS control plane (from app/)
│   └── inovcom/
│       ├── kernel/
│       ├── users/
│       ├── branding/
│       ├── configuration/
│       ├── items/
│       ├── clients/          # ERP lineage initially
│       ├── clients-bat/      # OR clients with adapter — temporary
│       ├── sales/            # POS
│       ├── stock/
│       ├── ... (retail modules)
│       ├── pressing/         # vertical
│       ├── offres/           # BAT vertical
│       ├── devis/
│       ├── projets/
│       └── ...
├── docs/
│   └── ARCHITECTURE_AUDIT.md
└── composer.json             # path repos + workspace tooling
```

**Principles:**

1. Hosts stay thin; **no business domain in `apps/*/app/Models` long-term**.
2. Modules remain Composer packages with `ModuleLifecycle`.
3. `config/modules.php` per app selects which packages appear.
4. Tenant DBs stay per-product initially (no cross-product tenant migration required on day 1).
5. Customers keep the same URLs, guards, and DB names during cutover.

---

## 9. Migration Roadmap (non-breaking)

### Phase 0 — Freeze & inventory (1–2 weeks)

- Freeze net-new duplicate packages in three trees.
- Establish this audit as the package catalogue of record (replace stale `docs/architecture.md` / `module-packages.md` in ERP/Pressing).
- Add CI fingerprinting: hash package trees ERP vs Pressing to prevent silent drift.
- Document per-customer: product, tenant codes, enabled modules, DB names.

**Customer impact:** none.

### Phase 1 — Monorepo scaffolding without behavior change (2–4 weeks)

- Create monorepo layout; move apps as-is under `apps/` (git subtree or history-preserving move).
- Introduce Composer path repositories pointing at **copied** packages (still three trees if needed).
- Shared CI: lint/test per app independently.
- Do **not** change tenant connection naming, route prefixes (`/admin`, `/app`), or module keys.

**Customer impact:** none if deploy artifacts stay separate.

### Phase 2 — Deduplicate ERP ↔ Pressing packages (4–8 weeks)

Priority order:

1. `kernel`, `users`, `items` (already identical / near-identical)
2. `clients`, `sales`, `stock`, `caisse`, `providers`, `purchases`
3. Finance stack: `quotations`, `invoicing`, `invoice_payments`, `debts`, `expenses`
4. Remaining modules; reconcile `invoicing` drift with regression tests on print/PDF & tax

For each package:

- Diff → choose winner → single package path → both apps require same path version.
- Run tenant migrations only if schema changed (prefer no schema change in this phase).
- Smoke-test: login, module install, one CRUD flow, one print.

**Customer impact:** zero if APIs/tables unchanged. Deploy Pressing and ERP from monorepo but still separate releases.

### Phase 3 — Extract shared platform host (`bproo/platform`) (4–6 weeks)

- Move tenancy, admin Livewire, module registry, jobs, middleware into a shared package.
- Keep product-specific: `tenant_types`, `modules.php`, assets/CSS, Pressing artisan commands, BAT scheduled SLA jobs.
- Align BAT onto subscription model **only if** product wants it; otherwise keep BAT plan fields behind a `BillingDriver` interface so ERP billing is optional for BAT.

**Customer impact:** none if middleware aliases and route names preserved.

### Phase 4 — Attach verticals as add-ons (ongoing)

- Pressing: keep `bproo/pressing`; ensure it depends only on published contracts (`ItemsApi`, `Stock` services).
- BAT: leave vertical packages in place; optionally rename namespaces from `InovCom\*` consistency later (cosmetic, high churn — defer).
- ERP: pharmacy modules remain `tenant_types`-gated.

**Customer impact:** none.

### Phase 5 — Cross-vertical convergence (optional, 3–6+ months)

Only after Phases 1–4 are stable:

1. Introduce shared contracts for Invoicing, Purchasing, Stock movements.
2. Build adapters so BAT `facturation` and ERP `invoicing` both satisfy `InvoicingApi`.
3. Migrate schemas only with **versioned tenant migrations**, feature flags, and dual-read periods.
4. Unify Clients only after mapping ERP 360 fields ↔ BAT legal/CRM fields with a data migration playbook per tenant.

**Customer impact:** controlled; per-tenant migration windows; rollback via DB backups (already DB-per-tenant).

### Phase 6 — Product packaging / commercial SaaS (parallel)

- Single Module Marketplace catalogue across products.
- Plans that sell “module bundles” (Retail POS, Pressing, BAT Chantier).
- Optional: one marketing site; still separate app entrypoints or multi-domain routing.

---

## 10. Risk Register

| Risk | Why it matters | Mitigation |
|---|---|---|
| Forcing BAT `clients`/`stock` into ERP schemas | Breaks chantier or retail data models | Keep forks; share contracts first |
| Silent package drift ERP↔Pressing | Already started (`invoicing`) | Single package path + CI hash |
| Dual permission catalogues (BAT) | Role/permission mismatches | One seeder path + vertical permission providers |
| Renaming route names / module keys | Bookmarks, docs, enabled modules | Freeze public keys forever |
| Big-bang rewrite | Explicitly rejected | Package extraction only |
| CSS divergence (Tailwind vs custom) | Shared Blade partials break | Shared PHP/Livewire first; theming adapters |
| Empty test suites | Regressions during merge | Add smoke tests per critical module before Phase 2 |

---

## 11. Recommended Package Ownership Map (end state)

### Always-on platform

`platform`, `kernel`, `users`, `branding`/`configuration`

### Retail / POS suite (ERP)

`items`, `clients`, `providers`, `sales`, `caisse`, `stock`, `inventory`, `purchases`, `losses`, `expenses`, `debts`, `reporting`, `quotations`, `invoicing`, `invoice_payments`, `returns`, `reservations`, `prospects`, `tickets`, `payroll`, `attendance`, `batches`, `prescriptions`

### Pressing suite

All needed retail foundation packages **+** `pressing`

### BAT / Construction suite

`clients` (BAT lineage or adapter), `items`, `offres`, `devis`, `projets`, `maintenance`, `facturation`, `achats`, `dms`, `planning`, `suivi`, `stock`, `logistique`, `rh`

### Admin

Not a tenant module — part of `platform` host UI in every product.

---

## 12. Answers to the Audit Questions (concise)

### What already exists?

A working **modular Composer-package architecture** with database-per-tenant SaaS admin in three products. Domain logic is already largely outside the Laravel `app/` folder.

### What can be extracted into reusable packages?

- **Immediately:** ERP↔Pressing shared `inovcom/*` set + shared platform host.
- **As vertical packages:** Pressing module; BAT chantier modules.
- **Later (contracts first):** Clients, Stock, Invoicing, Purchasing, HR across verticals.

### What is duplicated?

- Almost the entire ERP and Pressing codebases.
- Admin/tenancy pattern triplicated (BAT variant simpler on billing).
- Business *concepts* (clients, stock, invoices, purchases, HR) duplicated between retail and BAT with **different implementations**.

### What is app-specific?

- POS deep flows → ERP.
- Laundry workflow → Pressing.
- Chantier commercial→execution→SLA → BAT.

### How to migrate without breaking customers?

Monorepo → dedupe identical packages → extract platform → keep verticals separate → converge schemas only with contracts, flags, and per-tenant migrations. **Never rewrite; never change public module keys or tenant DB naming in early phases.**

---

## Appendix A — Package presence matrix

| Package | ERP | Pressing | BAT |
|---|---|---|---|
| kernel | Y | Y | Y |
| users | Y | Y | Y |
| branding | Y | Y | Y |
| configuration | Y | Y | — (via branding) |
| clients | Y | Y | Y* |
| items | Y | Y | Y* |
| stock | Y | Y | Y* |
| providers | Y | Y | — |
| sales | Y | Y | — |
| purchases | Y | Y | — |
| inventory | Y | Y | — |
| expenses | Y | Y | — |
| losses | Y | Y | — |
| debts | Y | Y | — |
| caisse | Y | Y | — |
| reporting | Y | Y | — |
| payroll | Y | Y | — |
| attendance | Y | Y | — |
| batches | Y | Y | — |
| prescriptions | Y | Y | — |
| quotations | Y | Y | — |
| invoicing | Y | Y | — |
| invoice_payments | Y | Y | — |
| tickets | Y | Y | — |
| returns | Y | Y | — |
| reservations | Y | Y | — |
| prospects | Y | Y | — |
| pressing (kamfo) | — | Y | — |
| offres | — | — | Y |
| devis | — | — | Y |
| projets | — | — | Y |
| maintenance | — | — | Y |
| facturation | — | — | Y |
| achats | — | — | Y |
| dms | — | — | Y |
| planning | — | — | Y |
| suivi | — | — | Y |
| rh | — | — | Y |
| logistique | — | — | Y |

\*Same Composer name, divergent implementation.

---

## Appendix B — Cross-cutting pattern checklist

For virtually every domain package audited:

| Concern | Pattern |
|---|---|
| Controllers | Rare — mostly PDF/print |
| Services | Present for non-trivial domains |
| Livewire | Primary UI |
| Routes | In ServiceProvider |
| Migrations | Tagged `inovcom-{module}-migrations` (or `pressing-migrations`) |
| Events | Host-level or rare |
| Policies | None |
| Permissions | `{Module}::defaultPermissions()` on install |
| Helpers | Support classes; almost no global helpers in packages |
| Repositories | None |

---

*End of architecture audit. No application code was modified in producing this document.*
