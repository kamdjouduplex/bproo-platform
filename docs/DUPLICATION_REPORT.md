# Bproo Platform — Duplication & Extraction Report

**Date:** 2026-07-28  
**Method:** MD5 file-hash comparison of every PHP file under packages + host `app/` layers across `bproo-erp`, `bproo-pressing`, and `bproo-bat`.  
**Scope:** Models, Services, Livewire, Controllers, Migrations, Events, Policies.  
**Constraint:** Analysis only — no code rewritten.

---

## 1. Executive Snapshot

| Comparison | Result |
|---|---|
| **ERP ↔ Pressing `inovcom/*` packages** | **91.6% identical** (545 / 595 PHP files) |
| **ERP ↔ Pressing host `app/`** | High (Models 100%, Jobs 100%, Events 100%, Services ~85%) |
| **ERP/Pressing ↔ BAT packages** | Low exact overlap; conceptual twins only |
| **Application Policies** | **0** in all three projects (none to dedupe) |
| **Package Events** | Almost none (BAT has 1: `DeliveryCompleted`) |
| **Host Events** | ERP/Pressing share 5 ModuleEvents; BAT adds workflow/quote/invoice events |

**Bottom line:** Duplicated business logic is overwhelmingly an **ERP ↔ Pressing clone problem**. BAT shares *feature names* and a few foundation classes, not copy-pasted implementations.

---

## 2. Inventory Compared

### 2.1 Host application (`app/`)

| Artifact | ERP | Pressing | BAT | ERP↔Press identical | ERP↔BAT identical |
|---|---:|---:|---:|---:|---:|
| Models | 10 | 10 | 6 | **100%** | 40% |
| Services | 13 | 13 | 7 | **84.6%** | 14.3% |
| Livewire | 14 | 14 | 16 | **71.4%** | 4.8% |
| Controllers | 4 | 5 | 4 | **80%** | 75% |
| Events (app only) | 5 | 5 | 10 | **100%** | 50% (same 5 ModuleEvents present) |
| Policies | 0 | 0 | 0 | n/a | n/a |
| Middleware | — | — | — | **80%** | 47.1% |
| Jobs | 5 | 5 | 3 | **100%** | 0% |
| Support | 8 | 7 | 0 | **87.5%** | n/a |
| Console commands | — | — | — | **84.8%** | 18.2% |
| Migrations (all DB) | 171 | 187 | 84 | (Pressing adds pressing/tenant extras) | divergent |

### 2.2 Packages (`packages/`)

| Artifact | ERP | Pressing | BAT |
|---|---:|---:|---:|
| Models | 100 | 117 | 43 |
| Services | 51 | 56 | 19 |
| Livewire | 182 | 225 | 108 |
| Controllers | 12 | 14 | 6 |
| Migrations | 133 | 149 | 50 |
| Events | 0 | 0 | 1 |
| Policies | 0 | 0 | 0 |

Pressing extras vs ERP: `packages/pressing` (~110 PHP files) + slightly richer package counts from that vertical.

---

## 3. Policies & Events (special note)

### Policies
**No application Policy classes exist** in any of the three products. Authorization is:

- `User::hasPermission()`
- Livewire `Concerns\Authorizes*`
- BAT: `Gate::before` + `AuthorizesWithTenant`

**Duplication action:** none for Policies. Standardize the concern/trait pattern inside a shared `users` / `platform` package.

### Events

| Location | Classes | Duplication |
|---|---|---|
| ERP/Pressing `app/Events/ModuleEvents` | `ClientCreated`, `ClientUpdated`, `ItemCreated`, `ItemUpdated`, `ItemDeleted` | **100% identical** ERP↔Press |
| BAT `app/Events` | Same 5 ModuleEvents **plus** `QuoteAccepted`, `QuoteSent`, `InvoiceOverdue`, `ProjectCreatedFromQuote`, `WorkflowTransitioned` | Shared ModuleEvents; BAT-only domain events |
| Packages | BAT `logistique`: `DeliveryCompleted` | Unique |

**Extraction:** Move ModuleEvents into `inovcom/kernel` or `bproo/platform`. Keep BAT workflow events in BAT vertical packages.

---

## 4. Duplicated Features Grouped

Scores use:

- **Duplication %** = identical PHP files / union of relative paths (ERP vs Pressing unless noted)
- **Complexity** = Low / Medium / High / Very High (models + services + LW + migration churn)
- **Extraction difficulty** = Easy / Medium / Hard / Very Hard
- **Risks** = customer / schema / coupling risks

---

### Group A — Exact / near-exact clones (ERP ↔ Pressing) → extract first

These are already packages; extraction means **one shared path package** consumed by both apps.

| Feature / package | Dup % | Models | Services | Livewire | Controllers | Migrations | Complexity | Extract | Risks | Convert how |
|---|---:|---|---|---|---|---|---|---|---|---|
| **batches** | 100% | 100% | 100% | 100% | — | 100% | Low | Easy | Low — pharmacy-only | Single `inovcom/batches`; both apps require same path |
| **debts** | 100% | 100% | 100% | 100% | — | 100% | Low | Easy | Low | Same |
| **inventory** | 100% | 100% | 100% | 100% | — | 100% | Low | Easy | Low | Same |
| **losses** | 100% | 100% | 100% | 100% | — | 100% | Low | Easy | Low | Same |
| **payroll** | 100% | 100% | 100% | 100% | 100% | 100% | High | Easy* | Medium — HR data sensitive; PDF payslips | Same; add smoke tests on payslip print |
| **prescriptions** | 100% | 100% | — | 100% | — | 100% | Low | Easy | Low | Same |
| **prospects** | 100% | 100% | 100% | 100% | — | 100% | Low | Easy | Low | Same |
| **providers** | 100% | 100% | 100% | 100% | — | 100% | Low | Easy | Low | Same |
| **purchases** | 100% | 100% | 100% | 100% | 100% | 100% | High | Easy* | Medium — foreign FX flows | Same; regression on PO/receipt |
| **reservations** | 100% | 100% | 100% | 100% | — | 100% | Medium | Easy | Medium — stock coupling | Same |
| **returns** | 100% | 100% | 100% | 100% | — | 100% | Very High | Easy* | Medium — enums/audit dense | Same; largest package — test returns→credit note |
| **sales (POS)** | 100% | 100% | 100% | 100% | 100% | 100% | Very High | Easy* | High for retail ops | Same; critical path: SalesForm cart + payments |
| **tickets** | 100% | 100% | 100% | 100% | — | 100% | Low | Easy | Low | Same |
| **items** | 100% | 100% | 100% | 100% | — | 100% | Medium | Easy | Medium — ItemsApi consumers | Same; freeze `ItemsApi` contract |
| **kernel** | 100% | — | — | — | — | — | Low | Easy | Low | Same; then merge BAT extras carefully |
| **branding** | 100% | — | — | 100% | — | — | Low | Easy | Low | Same |
| **clients** | **98.3%** | 100% | 100% | 94.1% | — | 100% | High | Easy | Low (1 Livewire file drift) | Diff the 1 diverging file → pick winner → share |
| **caisse** | **92.9%** | 100% | 100% | 50% | — | 100% | Medium | Easy | Medium — till money | Reconcile 1 PHP + Livewire view drift |
| **users** | **85%** | 66.7% | — | 80% | — | 100% | Medium | Medium | High — auth/RBAC | Diff User/Role Livewire; keep migrations identical |
| **quotations** | **82.6%** | 66.7% | 100% | 50% | 100% | 100% | Medium | Medium | Medium — commercial docs | Reconcile model/LW drift before share |
| **stock** | **77.8%** | 100% | **33.3%** | 70% | — | 100% | Medium | Medium | High — inventory integrity | Services diverged; merge StockService carefully |
| **invoice_payments** | **75%** | 100% | **0%** | 50% | 100% | 100% | Low | Medium | Medium — money | Service fully diverged — compare line-by-line |
| **attendance** | **72.2%** | 100% | 100% | **16.7%** | 100% | 100% | Medium | Medium | Low | UI drifted; keep services/models |
| **reporting** | **71.4%** | — | 100% | **0%** | — | — | Low | Medium | Low | Service same; Livewire UI forked |
| **expenses** | **69.2%** | 100% | 100% | **0%** | — | 100% | Low | Medium | Low | Models/services OK; rebuild shared LW from one side |
| **invoicing** | **67.2%** | 42.9% | **0%** | 58.3% | 100% | 94.4% | Very High | **Hard** | **High** — tax, DN, schedules | Largest drift; treat as merge project before share |
| **configuration** | **50%** | — | — | **0%** | — | — | Low | Medium | Low | Small surface; unify ConfigurationIndex |

\*Easy mechanically (already a package), but needs regression tests because complexity/risk is high.

---

### Group B — Host platform duplication (ERP ↔ Pressing)

| Feature | Dup % | Complexity | Extract | Risks | Convert how |
|---|---:|---|---|---|---|
| **Tenancy models** (`Tenant`, `Module`, `TenantModule`, …) | 100% Models | Medium | Medium | High if DB columns differ | Package `bproo/platform` with landlord models + migrations |
| **Subscription billing** (`Plan`, `Subscription`, payments) | Models identical | High | Medium | High — money/SaaS | Include in `bproo/platform`; BAT stays on simple plan fields via interface |
| **Module registry / marketplace services** | ~85% Services | High | Medium | High — install/uninstall | Extract `ModuleRegistry`, jobs, middleware |
| **Admin Livewire** | ~71% | Medium | Medium | Medium — ops UI | Shared Admin Livewire namespace in platform package |
| **Tenant Jobs** | 100% | Medium | Easy | Medium — queues | Move Jobs into platform |
| **Print / document Support** | 87.5% | Medium | Easy | Medium — PDF layout | `bproo/printing` or platform Support |
| **ModuleEvents** | 100% | Low | Easy | Low | Move to kernel/platform |
| **Auth Controllers** | ~80% | Low | Easy | Medium — login | Shared Auth controllers; Pressing may keep LocaleController |

---

### Group C — Conceptual twins (ERP/Pressing ↔ BAT) — **not** copy-paste

Exact file duplication is near **0%** for business packages. Class-name overlap:

| Feature pair | Name overlap | Exact content | Dup (business logic) estimate | Complexity | Extract | Risks | Convert how |
|---|---:|---:|---:|---|---|---|---|
| **users** ↔ **users** | 94.4% | 6 files (mostly migrations) | **~55–70%** structural | Medium | Medium | High — permission catalogues differ | Shared package core + `PermissionCatalog` provider per vertical |
| **items** ↔ **items** | 60% | 10 (migrations + Brand/Category) | **~40–50%** | Medium | Medium | Medium | Shared catalogue core; ERP keeps sets/list-config as extensions |
| **branding** ↔ **branding** | 100% | 1 | **~60%** | Low | Easy | Low | One package; BAT maps module key `configuration` |
| **kernel** ↔ **kernel** | 41.7% | 2 (`ClientsApi`/`ItemsApi` related) | **~35%** | Medium | Medium | Medium | Base = ERP kernel; port BAT `WorkflowStateMachine`, `Auditable`, `AuditLog` as optional traits |
| **clients** ↔ **clients** | 10.6% | **0** | **~15–25%** concept only | High vs Low | **Very Hard** | **Very High** | Do **not** merge schemas. Shared `ClientsApi` contract + adapters |
| **stock** ↔ **stock** | 12.2% | **0** | **~10–20%** | Medium | **Very Hard** | **Very High** | Shared movement interface later; keep Product/Warehouse vs Item/Location forks |
| **quotations** ↔ **devis** | 3.8% | 0 | **~10%** | High | Very Hard | High | Extract shared line/tax/PDF helpers only |
| **invoicing** ↔ **facturation** | 9.1% | 0 | **~15%** | High | Very Hard | High | Shared `InvoicingApi` + print helpers; keep packages separate |
| **purchases** ↔ **achats** | 5% | 0 | **~10%** | High | Very Hard | High | Same pattern |
| **payroll** ↔ **rh** | 14.6% | 0 | **~20%** | High vs Medium | Hard | Medium | Optional later `Employee` core; not phase 1 |
| **sales** ↔ **facturation** | 4.7% | 0 | **~5%** | — | Skip | — | Different domains (POS vs project invoicing) |

---

### Group D — Not duplicated (vertical-only) — do **not** force-share

| Feature | Where | Action |
|---|---|---|
| **Pressing** (agences, orders, workflow, loyalty, QR, …) | Pressing only (~110 PHP) | Keep as `bproo/pressing` vertical package |
| **offres, devis, projets, maintenance, planning, suivi, dms, logistique, achats, facturation, rh** | BAT only (~187 PHP) | Keep as BAT vertical packages |
| **batches, prescriptions** | ERP/Pressing (pharmacy) | Shared retail package; disabled for pressing/BAT tenants |
| **caisse, sales POS** | ERP/Pressing | Shared; not for BAT |

---

## 5. Priority Extraction Queue

Ordered by **(duplication × business value) / (difficulty × risk)**:

| Priority | Feature | Why |
|---:|---|---|
| 1 | `kernel`, `items`, `batches`, `debts`, `inventory`, `losses`, `providers`, `prospects`, `prescriptions`, `reservations`, `tickets`, `sales`, `purchases`, `payroll`, `returns` | 100% identical — delete duplicate trees immediately |
| 2 | `clients`, `caisse`, `branding` | ≥93% — resolve tiny diffs |
| 3 | Host Jobs, ModuleEvents, Support print helpers | 100%/high host dup — platform package |
| 4 | `users`, host Middleware/Services/Admin Livewire | Auth-critical; reconcile before share |
| 5 | `quotations`, `stock`, `attendance`, `reporting`, `expenses`, `invoice_payments` | 70–85% — merge sessions |
| 6 | `invoicing` + `configuration` | Highest drift among shared packages |
| 7 | `bproo/platform` host extraction | After packages stabilize |
| 8 | BAT `users`/`items`/`kernel` alignment | Structural only |
| 9 | Cross-vertical clients/stock/invoicing | Contracts only — years/quarters later |

---

## 6. How to Convert Each Group into a Reusable Package

### Pattern already in use (keep it)

Every domain feature is already shaped as:

```
packages/inovcom/{feature}/
  composer.json
  src/{Feature}ServiceProvider.php
  src/{Feature}Module.php          # ModuleLifecycle + defaultPermissions()
  src/Models/
  src/Services/
  src/Http/Livewire/
  src/Http/Controllers/            # print only
  database/migrations/
  resources/views/
```

**Conversion for Group A** is not a rewrite — it is **deduplication**:

1. Choose canonical tree (prefer ERP if Pressing-only drift is UI-only; prefer the side with newer migrations if schema advanced).
2. Place once under monorepo `packages/inovcom/{feature}`.
3. Both `apps/erp` and `apps/pressing` path-require the same package.
4. Keep `module:{key}` middleware and migration tags unchanged.
5. Add a fingerprint CI job: fail if a second copy of the package appears.

### Platform package (`bproo/platform`)

Move from host `app/`:

- Models: Tenant, Module, TenantModule, ModuleEvent, TenantSetting, Plan/Subscription*  
- Services: TenantManager, ModuleRegistry, TenantProvisioner, …  
- Middleware: SetTenantConnection, EnsureModuleEnabled, …  
- Jobs, Admin Livewire, ModuleEvents  

\*Subscription stack optional via interface so BAT can keep simple plans.

### Vertical packages (unchanged shape)

- `bproo/pressing` — already correct  
- BAT packages — already correct; rename vendor later if desired (`inovcom/*` → `bproo/bat-*`) without logic changes  

### Cross-vertical (Group C) — contracts, not merges

```
inovcom/kernel Contracts:
  ClientsApi, ItemsApi, (+ future StockApi, InvoicingApi)

ERP clients package  --implements--> ClientsApi
BAT  clients package --implements--> ClientsApi   (adapter over lean schema)
```

No shared migrations until a deliberate data model unification project.

---

## 7. Risk Summary

| Risk level | Features | Mitigation |
|---|---|---|
| **Low** | Identical 100% retail packages, ModuleEvents, branding | Path-share + smoke CRUD |
| **Medium** | users, stock, quotations, caisse, host admin | File-by-file diff; keep migration hashes; permission regression |
| **High** | sales/POS, invoicing drift, purchases FX, returns, platform tenancy | Feature tests; dual deploy from monorepo before deleting old trees |
| **Very High** | Merging BAT clients/stock/facturation into ERP schemas | **Do not do** in extraction phase |

---

## 8. Estimated Effort (indicative)

| Workstream | Effort | Outcome |
|---|---|---|
| Share all 100% identical packages | 1–2 weeks | Remove ~half of Pressing’s inovcom tree |
| Reconcile 70–99% packages | 2–4 weeks | Single source for remaining retail modules |
| Extract `bproo/platform` from ERP/Pressing hosts | 3–5 weeks | One admin/tenancy codebase |
| BAT foundation align (users/items/kernel) | 2–4 weeks | Shared foundation without schema merge |
| Cross-vertical business unification | 3–6+ months | Optional; contracts first |

---

## 9. What Is *Not* Duplicated Business Logic

Do not spend extraction effort here:

- Pressing Kanban / tri / loyalty / QR / WhatsApp dispatcher  
- BAT offres → devis → projets → maintenance → suivi pipeline  
- BAT DMS, planning, logistique  
- Application Policies (do not exist)  
- Package-level Events (essentially unused pattern)

---

## 10. One-Page Recommendation

1. Treat **ERP and Pressing as one product family** with a Pressing add-on.  
2. **91.6% of inovcom PHP is already duplicated** — extract by path-sharing, not rewriting.  
3. Hardest shared package to unify: **`invoicing`** (67% dup, services 0% identical).  
4. Treat BAT as a **sibling vertical** on a shared platform/kernel/users — not a clone of retail modules.  
5. Zero Policies to consolidate; consolidate **permission concerns** instead.  
6. Move the five shared **ModuleEvents** into the platform/kernel package first (easy win).

---

*Report generated from full-tree hash comparison. Companion: `ARCHITECTURE_AUDIT.md`.*
