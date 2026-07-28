# Bproo Platform — Incremental Migration Roadmap

**Version:** 1.1 (Architecture-aligned)  
**Date:** 2026-07-28  
**Status:** Ready to start at Phase 0  
**Authority:** Must comply with `BPROO_PLATFORM_ARCHITECTURE_v1.md`  
**Sources:** Architecture v1.0 · `ARCHITECTURE_AUDIT.md` · `DUPLICATION_REPORT.md` · `IDEAL_MODULAR_ARCHITECTURE.md`

---

## Review vs Architecture v1.0

The original roadmap (v1.0) was sound for **dedupe + extract**. Architecture v1.0 adds goals that required these adjustments:

| Architecture requirement | Roadmap v1.0 gap | v1.1 fix |
|---|---|---|
| **Control Center** as separate app (`apps/control-center`) | Phase 4 only extracted Admin *into* product hosts | Split into **M4** (platform packages) + **M4b** (Control Center MVP) |
| Official folders `packages/{platform,shared,verticals,ui}` | Mixed `inovcom/` naming forever | Keep `inovcom/*` Composer names early; physical paths move under official folders from M2; rename Composer in M7 |
| **Laravel 12+** target | Missing | Parallel track **L10→L11→L12** (never blocks M0–M3) |
| Company vs Tenant language | Tenant-only wording | Dual support: code may keep `tenant` connection; CC entity = Company; URLs dual-read `?tenant=` / `?company=` later |
| Platform CRM ≠ company CRM | Not explicit | CC owns Bproo sales pipeline only; tenant `crm` stays in company DB |
| Do not over-split `mail`/`sms`/`whatsapp` | Risk of nano-packages in M7 | Notifications channels stay inside `notifications` until justified |
| POS = capability not separate DB | Implicit | Explicit: POS SKU = `sales` + `cash-register`; optional `apps/pos` kiosk much later |
| BAT financial docs stay vertical | Present | Unchanged — reinforced |
| ADRs for divergence | Missing | `docs/adr/` required from M0 |
| UI design system | Missing | **M7b** thin `packages/ui` after SOLID splits — not before |

### Verdict

**Start is approved** at Phase 0. Do **not** start with Control Center, Laravel 12, or SOLID renames. Those come after ERP↔Pressing packages are unified.

---

## 0. Non-Negotiable Constraints

| Constraint | Implication |
|---|---|
| Production customers live today | No big-bang cutover; normal releases only |
| Cannot stop production | Each phase = shippable increment |
| Cannot rewrite everything | Move / dedupe / extract; preserve contracts |
| Control Center DB purity | Never move company business data into CC DB |
| Incremental only | One risk class per phase; exit criteria before next |
| Rollback always possible | Previous image + backups; prefer additive changes |

### Current applications

| Product | Codebase today | Target host |
|---|---|---|
| **ERP** | `bproo-erp` | `apps/erp` |
| **POS** | Inside ERP (`sales` + `caisse`) | Capability of ERP (optional `apps/pos` later) |
| **Pressing** | `bproo-pressing` | `apps/pressing` |
| **BAT** | `bproo-bat/bproo-bat` | `apps/bat` |
| **Admin** | `/admin` in each product | → platform packages → **Control Center** |

### Golden rules

1. Freeze: route names (`tenant.*`), module keys, permission strings, DB naming (`inovcom_tenant_{code}` until an ADR changes it).  
2. No company data migration unless a phase says so (Phases 0–8: none).  
3. Separate deploys per product until CC cutover.  
4. Smoke on staging clone before production.  
5. Never delete old package trees until the *next* release is stable.  
6. Any deviation → ADR in `docs/adr/`.

---

## 1. Milestone Map (v1.1)

```
M0   Freeze & inventory              Week 0–2     ◄── START HERE
M1   Monorepo scaffold               Week 2–6
M2   Share 100% identical packages   Week 6–10
M3   Reconcile drifted packages      Week 10–16
M4   Extract platform packages       Week 16–22
M4b  Control Center MVP              Week 22–28
M5   Verticals as plugins            Week 28–34
M6   BAT foundation align            Week 34–40
M7   SOLID package splits            Week 40–48
M7b  UI kit (packages/ui)            Week 48–52
M8   Cross-vertical contracts        Week 52–60+
M9   Optional schema convergence     Later
L*   Laravel 10→11→12                Parallel from M3+
```

**Modular platform “done” for daily work:** M7 (+ M4b CC usable by ops).  
**Architecture north-star complete:** M8.  
**Schema unity:** optional M9 — not required.

| Milestone | Customer-visible | Stop-the-line if… |
|---|---|---|
| M0 | None | Cannot inventory companies/modules |
| M1 | None | Deploy pipeline breaks |
| M2–M3 | None | POS checkout or Pressing order regresses |
| M4 | None | Provisioning / company login fails |
| M4b | Ops may use new CC URL | Parity gap blocks billing/provision |
| M5–M6 | None | Vertical install or devis→projet breaks |
| M7 | None (aliases) | Permission checks fail |
| M8–M9 | Opt-in only | Contract/schema failure |

---

## Phase 0 — Freeze & Inventory *(START)*

### Objectives
- Freeze duplicate package creation across ERP / Pressing / BAT.
- Tag production baselines.
- Export company (tenant) + module inventory per product.
- Create smoke runbooks + ADR folder.
- Add package fingerprint CI (ERP vs Pressing).

### Estimated duration
**1–2 weeks**

### Risks
| Risk | Severity | Mitigation |
|---|---|---|
| Incomplete inventory | High | Export from each product Admin |
| Drift during later phases | Medium | Fingerprint CI + “no new inovcom copy” rule |
| No staging clones | High | Anonymized DB clones before M2 |

### Files affected
```
docs/adr/0000-template.md
docs/runbooks/smoke-erp.md
docs/runbooks/smoke-pressing.md
docs/runbooks/smoke-bat.md
docs/runbooks/smoke-admin.md
tools/fingerprint/          # or CI workflow
# No application PHP changes
```

### Packages extracted
None.

### Testing strategy
- Verify current tagged builds on staging.
- Walk smoke runbooks once manually; fix gaps in docs only.

### Rollback strategy
N/A. Tags: `erp-prod-baseline`, `pressing-prod-baseline`, `bat-prod-baseline`.

### Expected result
- Go/no-go signed for M1.  
- **M0 complete.**

### Exit criteria (must all be true)
- [ ] Three production tags exist  
- [ ] Company/module inventory spreadsheet or export exists  
- [ ] Four smoke runbooks drafted  
- [ ] Fingerprint CI fails if ERP/Pressing packages diverge on tracked set  
- [ ] Team agrees: no new features that duplicate packages during M1–M3  

---

## Phase 1 — Monorepo Scaffold (Zero Behavior Change)

### Objectives
- Layout matches Architecture §9 without unifying packages yet:
  ```
  apps/erp, apps/pressing, apps/bat
  docs/   (already)
  packages/  (empty or placeholders — packages still inside apps)
  ```
- Keep three independent deploy pipelines.

### Estimated duration
**2–4 weeks**

### Risks
Broken CI/deploy paths; secret leakage via shared env — mitigate with per-app env and path-only moves.

### Files affected
Move `bproo-erp` → `apps/erp`, `bproo-pressing` → `apps/pressing`, `bproo-bat/bproo-bat` → `apps/bat`; update composer paths, Docker, CI.

### Packages extracted
None (still duplicated inside each app).

### Testing strategy
Staging deploy each app from monorepo; Admin + company login + one CRUD.

### Rollback strategy
Redeploy from old remotes/tags; abandon monorepo commit if needed.

### Expected result
Single repo; customers notice nothing. **M1 complete.**

---

## Phase 2 — Share 100% Identical Packages (ERP ↔ Pressing)

### Objectives
- Canonical physical trees under `packages/shared/` (or `packages/inovcom/` temporary).
- Keep Composer package names `inovcom/*` (no rename yet — Architecture allows transition).
- Include POS core: `sales` (100% identical).

### Estimated duration
**3–4 weeks**

### Risks
POS regression (critical); autoload/provider discovery — keep names, change paths only.

### Files affected
```
packages/shared/{kernel,items,sales,purchases,providers,debts,inventory,
  losses,payroll,batches,prescriptions,prospects,reservations,tickets,
  returns,branding}/
apps/erp/composer.json
apps/pressing/composer.json
# delete in-app copies after stable release
```

### Packages extracted (canonicalized)
`kernel`, `items`, `sales`, `purchases`, `providers`, `debts`, `inventory`, `losses`, `payroll`, `batches`, `prescriptions`, `prospects`, `reservations`, `tickets`, `returns`, `branding`

> Note: Architecture maps `kernel`→`platform/core` later (M4/M7). Do not rename in M2.

### Testing strategy
ERP/POS: caisse open → sale (cash + mobile money) → park → return → print → stock.  
Pressing: order lifecycle + consumables if using items/stock.  
Admin: module install still works.

### Rollback strategy
Point composer back to in-app trees; no DB rollback.

### Expected result
~16 packages single-sourced. **M2 complete.**

---

## Phase 3 — Reconcile Drifted ERP ↔ Pressing Packages

### Objectives
Merge near-duplicates: `clients`, `caisse`, `users`, `stock`, `quotations`, `invoice_payments`, `attendance`, `reporting`, `expenses`, `invoicing`, `configuration`.

### Estimated duration
**4–6 weeks**

### Risks
`invoicing` (hardest), `stock` services, `users` authz — mini-projects with PDF/movement golden tests.

### Files affected
Those packages under `packages/shared/` + composer; `docs/diffs/{package}.md`.

### Packages extracted
Final shared retail set (all former `inovcom/*` used by both apps).

### Testing strategy
Per-package CRUD + prints; 1 ERP + 1 Pressing pilot for 1 week.

### Rollback strategy
Pin package SHA; reverse migration only if one was shipped (avoid).

### Expected result
Zero duplicate retail trees ERP↔Pressing. **M3 complete.**

**Laravel note:** After M3 stability, start **L10→L11** on a branch for one app (prefer Pressing or ERP staging only).

---

## Phase 4 — Extract Platform Packages (not full Control Center yet)

### Objectives
Extract host control plane into `packages/platform/*` per Architecture §10, **still mounted by each product’s `/admin`**:

- `platform/core` (from kernel + contracts)  
- `platform/tenancy`  
- `platform/modules`  
- `platform/billing` (+ `BillingDriver` for BAT later)  
- `platform/admin` (Livewire Admin suite)  
- `platform/auth`  
- `platform/printing`  
- partial `notifications` (bell)

Do **not** yet delete product `/admin` routes — Admin package serves them.

### Estimated duration
**4–6 weeks**

### Risks
Provisioning failure; middleware order; Livewire discovery — aliases for one release.

### Files affected
`apps/*/app/{Models,Services,Livewire/Admin,Http/Middleware,Support,Jobs}` → platform packages; thinner `routes/web.php`.

### Packages extracted
Listed above.

### Testing strategy
Create company → provision → modules → plans/payments (ERP/Pressing) → company login → module 403.

### Rollback strategy
Previous image; class aliases.

### Expected result
Shared platform code; Admin still per-product URL. **M4 complete.**

---

## Phase 4b — Control Center MVP

### Objectives
Stand up `apps/control-center` as Architecture §7 brain for **ops parity** with today’s Admin:

**MVP must include:** Companies · provisioning status · modules enable · plans/subscriptions/payments · health · module events · platform users login  

**Explicitly defer (post-MVP):** Platform CRM pipeline, marketplace third-party, custom domains UI polish, support tickets, advanced analytics, Takoly API keys (stubs OK).

### Estimated duration
**4–6 weeks**

### Risks
Ops confusion (two Admin ULs); parity gaps — run CC + product Admin in parallel; CC writes to **same** landlord DB schema initially (expand/contract).

### Files affected
```
apps/control-center/          # new Laravel host
packages/platform/*           # consumed
deployment/                   # new service
docs/runbooks/smoke-control-center.md
```

### Packages extracted
`platform/companies` façade if needed; reuse billing/modules/tenancy/admin UI.

### Testing strategy
Provision company from CC → open in ERP/Pressing/BAT hosts → billing suspension from CC respected.

### Rollback strategy
Ops continues on product `/admin`; disable CC deploy.

### Expected result
Single ops entrypoint usable; product `/admin` deprecated but available. **M4b complete.**

---

## Phase 5 — Verticals as Plugins

### Objectives
`packages/verticals/pressing`, `packages/verticals/bat-*`. Verticals depend on Interfaces only. POS remains ERP capability.

### Estimated duration
**4–6 weeks**

### Risks
Concrete model coupling in Pressing consumables; path breaks — adapters + composer-only moves.

### Packages extracted
`verticals/pressing`, `bat-offers`, `bat-quotes`, `bat-projects`, `bat-maintenance`, `bat-planning`, `bat-site-tracking`, `bat-logistics`, `bat-purchasing`, `bat-invoicing`

### Testing strategy
Full Pressing + BAT commercial/field cycles; ERP smoke unchanged.

### Rollback strategy
Old package paths in composer.

### Expected result
Clear plugin boundary. **M5 complete.**

---

## Phase 6 — BAT Foundation Align (Safe)

### Objectives
BAT uses shared `platform/core` (incl. workflow/audit traits), `users`/`permissions` injection, `settings`, optional `catalogue`. **No** merge of clients/stock/facturation/achats schemas (ADR-009).

### Estimated duration
**4–6 weeks**

### Risks
Permission lockout; workflow regressions — dual-seed, `*` admin, transition tests.

### Expected result
Shared foundation; BAT vertical finance stays local. **M6 complete.**

---

## Phase 7 — SOLID Splits (Architecture package names)

### Objectives
Rename/split toward Architecture catalogue **with aliases**:

| From | To |
|---|---|
| users (mixed) | `users` + `permissions` |
| branding + configuration | `settings` |
| clients + prospects | `crm` |
| stock + inventory counts | `inventory` + `inventory-count` |
| caisse | `cash-register` |
| invoice_payments | `payments` |
| payroll | `hr` |
| dms | `media` |
| inovcom Composer names | `bproo/*` via replace |

**Forbidden:** renaming module keys / permission keys without dual-read ADR.

### Estimated duration
**6–8 weeks**

### Expected result
Folder layout matches Architecture. **M7 complete.**

---

## Phase 7b — UI Kit

### Objectives
Extract `packages/ui/{core,forms,tables,charts,themes}` from repeated Blade patterns. No domain logic.

### Estimated duration
**2–4 weeks**

### Expected result
New verticals can assemble UI faster. **M7b complete.**

---

## Phase 8 — Cross-Vertical Contracts

### Objectives
Stable Interfaces: `ClientsApi`, `ItemsApi`, `StockApi`, `InvoicingApi`, `PurchasingApi`. BAT adapters. Reports depend on Interfaces.

### Estimated duration
**8–12 weeks**

### Expected result
True DIP across verticals. **M8 complete.**

---

## Phase 9 — Optional Schema Convergence

Only if product strategy demands one data model. Dual-write, per-company windows, backups. **Not required** for Architecture compliance.

---

## Parallel Track — Laravel Upgrades (L*)

| Step | When | Scope |
|---|---|---|
| L10 → L11 | After M3 stable | One app staging → prod; then others |
| L11 → L12 | After M4 or M5 | Same gated approach |
| PHP 8.3+ | With L11/L12 | Align with Architecture baseline |

Never combine a major Laravel upgrade with `invoicing` merge or CC cutover in the same release.

---

## Ready to Start — Checklist

### We are ready when

1. You approve **start Phase 0 only** (this message / explicit go).  
2. Someone can tag the three production baselines.  
3. Access exists to export companies/modules from each Admin.  
4. Staging (or a safe clone) is available before M2.  
5. Agreement: **no rewrite**, **no CC build in week 1**, **no Laravel jump in week 1**.

### First week of execution (Phase 0 tasks)

| # | Task | Owner |
|---|---|---|
| 1 | Tag `*-prod-baseline` on each product repo | DevOps / lead |
| 2 | Export company + modules inventory | Ops |
| 3 | Draft four smoke runbooks under `docs/runbooks/` | Eng |
| 4 | Add ADR template + `docs/adr/0001-record-architecture-v1.md` | Arch |
| 5 | Add ERP↔Pressing package fingerprint check | Eng |
| 6 | Announce freeze: no new duplicate packages | Lead |

### Explicitly out of scope for the start

- Building Control Center UI  
- Renaming to `bproo/*`  
- Merging BAT clients/stock  
- Laravel 12 upgrade  
- Takoly / MaxiPanier / Hotel  

---

## Standing Testing & Rollback

Unchanged from v1.0 in spirit:

- Smoke every production deploy (ERP/POS, Pressing, BAT, Admin/CC).  
- Pilot one company per product before fleet rollout on risky phases (M2+, M4, M4b).  
- Rollback = previous image + composer pin; Phases 0–8 need no tenant DB restore if we keep additive-only discipline.

---

## Document control

| Version | Date | Notes |
|---|---|---|
| 1.0 | 2026-07-28 | Initial roadmap |
| 1.1 | 2026-07-28 | Aligned with Architecture v1.0: CC (M4b), UI (M7b), Laravel track, official folders, start gate |

*When you say go, we begin Phase 0 — freeze, tags, inventory, runbooks, fingerprint CI. No production code behavior change in that phase.*
