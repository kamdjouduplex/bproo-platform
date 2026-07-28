# Bproo Platform Architecture

## Official Technical Reference — Version 1.0

| Field | Value |
|---|---|
| **Document** | Bproo Platform Architecture Handbook |
| **Version** | 1.0.0 |
| **Status** | Approved as technical north star |
| **Date** | 2026-07-28 |
| **Owner** | Bproo Dev — Architecture / CTO Office |
| **Audience** | Engineers, tech leads, DevOps, product |
| **Companion docs** | `ARCHITECTURE_AUDIT.md`, `DUPLICATION_REPORT.md`, `IDEAL_MODULAR_ARCHITECTURE.md`, `MIGRATION_ROADMAP.md` |

> **Authority:** This document is the official architecture for all current and future Bproo products. Where implementation conflicts with this handbook, raise an Architecture Decision Record (ADR); do not silently diverge.

---

# Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Vision & Product Portfolio](#2-vision--product-portfolio)
3. [Architecture Principles](#3-architecture-principles)
4. [Design Decisions (ADR Summary)](#4-design-decisions-adr-summary)
5. [System Context](#5-system-context)
6. [Multi-Tenancy Model](#6-multi-tenancy-model)
7. [Bproo Control Center](#7-bproo-control-center)
8. [Product Assembly Model](#8-product-assembly-model)
9. [Monorepo & Folder Structure](#9-monorepo--folder-structure)
10. [Package Architecture](#10-package-architecture)
11. [Domain Model](#11-domain-model)
12. [Application Runtime Architecture](#12-application-runtime-architecture)
13. [Shared UI System](#13-shared-ui-system)
14. [Event-Driven Design & CQRS](#14-event-driven-design--cqrs)
15. [Coding Standards](#15-coding-standards)
16. [Security Architecture](#16-security-architecture)
17. [Deployment & Operations](#17-deployment--operations)
18. [Incremental Migration Strategy](#18-incremental-migration-strategy)
19. [Risks & Mitigations](#19-risks--mitigations)
20. [Future Roadmap](#20-future-roadmap)
21. [Best Practices Checklist](#21-best-practices-checklist)
22. [Glossary](#22-glossary)
23. [Appendix — Package Catalogue Detail](#23-appendix--package-catalogue-detail)

---

# 1. Executive Summary

Bproo is building an **ecosystem of vertical business applications** (ERP, POS, Pressing, BAT, Hotel, School, Clinic, …) plus public consumer apps (Takoly, MaxiPanier, mobile) on **one modular Laravel platform**.

### What we already have

Three production Laravel 10 applications (`erp`, `pressing`, `bat`) already use:

- Database-per-tenant PostgreSQL
- Composer path packages (`inovcom/*`)
- Module enablement per company
- Livewire UI
- Custom RBAC
- A duplicated Admin/control plane inside each app

ERP ↔ Pressing share **~92% identical** package code. BAT shares the *pattern*, not the implementations, for several domains (clients, stock, invoicing).

### What we will build

| Layer | Role |
|---|---|
| **Bproo Control Center** | Single brain: companies, billing, licenses, modules, deployments, platform CRM |
| **Product apps** | Thin hosts that enable packages (ERP, Pressing, BAT, …) |
| **Shared packages** | Reusable bounded contexts (CRM, catalogue, inventory, sales, …) |
| **Vertical packages** | Product-specific domains (pressing workflow, chantier, hotel PMS, …) |
| **Public apps** | Separate deployables consuming platform APIs |

### What we will not do

- Rewrite production apps from scratch
- Put tenant business data in the Control Center database
- Merge BAT and retail schemas in phase 1
- Mandate Repository Pattern everywhere
- Mandate full CQRS everywhere
- Create dozens of nano-packages that increase coordination cost without reuse value

### Target outcomes

- **≥90% package reuse** when launching a new vertical (Hotel, School, …)
- **Independent install** of modules per company
- **Independent testability** of packages
- **Horizontal scale** of app nodes, queue workers, and tenant databases
- **Zero forced downtime** during migration beyond normal releases

---

# 2. Vision & Product Portfolio

## 2.1 Company

**Bproo Dev** — platform company selling and operating modular business software for African and international markets (mobile money, multi-branch, French-first UX).

## 2.2 Current products

| Product | Domain | Today |
|---|---|---|
| **ERP** | Retail / general business | `bproo-erp` |
| **POS** | Point of sale (capability of ERP) | `sales` + `cash-register` packages |
| **Pressing** | Dry-cleaning / laundry ops | `bproo-pressing` + pressing vertical |
| **BAT** | Construction / renovation / maintenance | `bproo-bat` |

## 2.3 Future products (assembled from packages)

Hotel · School · Clinic · Restaurant · Pharmacy (deepening) · E-Commerce · Marketplace · CRM (standalone) · HR · Payroll · Accounting · Manufacturing

## 2.4 Public applications

| App | Nature | Coupling |
|---|---|---|
| **Takoly** | Public / consumer | API to Control Center + product backends |
| **MaxiPanier** | Public / commerce | Same |
| **Mobile apps** | Native / PWA | OAuth2 / API tokens; never direct DB |

Public apps **never** embed tenant business packages. They talk to published HTTP APIs.

## 2.5 Reuse vision

```
New product = Control Center license
            + Product host (thin)
            + Shared packages (enabled)
            + 1..N vertical packages
            + Theme / modules.php
```

Target: **90% existing packages**, **10% new vertical code**.

---

# 3. Architecture Principles

## 3.1 Non-negotiable principles

| # | Principle | Meaning |
|---|---|---|
| P1 | **Package-first** | Business capability lives in a Composer package, not in `apps/*/app` |
| P2 | **Database-per-company** | One PostgreSQL database per tenant company for business data |
| P3 | **Control Center purity** | Central DB holds platform concerns only — never tenant stock/sales/chantiers |
| P4 | **Depend on abstractions** | Cross-package calls use Interfaces / Events / DTOs |
| P5 | **Vertical isolation** | Pressing/BAT/Hotel must not be imported by shared packages |
| P6 | **Stable public contracts** | Module keys, permission keys, route names, tenant DB naming are semver-stable |
| P7 | **Incremental evolution** | Extract and dedupe; do not rewrite |
| P8 | **Test at package boundary** | Each package has its own test suite |
| P9 | **Security by isolation** | Tenant DB credentials scoped; no cross-tenant queries |
| P10 | **Observability by default** | Structured logs, metrics, audit for money and auth |

## 3.2 SOLID

| Letter | Application |
|---|---|
| **S** | One package = one bounded context (`sales` ≠ `cash-register` ≠ `payments`) |
| **O** | New verticals add packages; core Interfaces stay stable |
| **L** | Any `StockApi` implementation must be substitutable |
| **I** | Small Interfaces (`ItemsApi`, `ClientsApi`) — no god facades |
| **D** | Domain services depend on Interfaces, not Eloquent models of other packages |

## 3.3 Clean Architecture (Laravel mapping)

```
┌─────────────────────────────────────────────┐
│  UI (Livewire / Blade / HTTP Controllers)   │  Framework
├─────────────────────────────────────────────┤
│  Application (Actions, Queries, Listeners)  │  Use cases
├─────────────────────────────────────────────┤
│  Domain (Models, Policies, Domain Events)   │  Pure-ish
├─────────────────────────────────────────────┤
│  Infrastructure (Eloquent, Redis, PDF, S3)  │  Details
└─────────────────────────────────────────────┘
```

Laravel is the delivery framework — **not** the domain. Packages own domain + application layers; the host app is the composition root.

## 3.4 DDD

- **Bounded contexts** = packages (or tightly related package pairs).
- **Ubiquitous language** per vertical (Pressing: Order/Tri/Agence; BAT: Offre/Devis/Chantier; Retail: Sale/Caisse).
- **Anti-corruption layers** between retail and BAT when sharing names (`Invoice` retail ≠ `Invoice` BAT) until/unless schemas converge.
- **Aggregates** stay small (e.g. `Sale` + lines; not “entire ERP”).

## 3.5 Where we challenge common dogma

| Dogma | Bproo position |
|---|---|
| Repository on every model | **Reject.** Eloquent *is* the repository for most CRUD. Introduce repositories only for complex querying, multi-store read models, or swappable persistence. |
| CQRS everywhere | **Reject.** Use CQRS for reporting, search indexes, dashboards — not for simple Livewire forms. |
| Microservices now | **Reject.** Modular monolith + DB-per-tenant scales first. Extract services only when a bounded context needs independent deploy/scale (e.g. notifications, search). |
| One universal data model for all verticals | **Reject early.** Shared *contracts*; separate *schemas* until product reality proves convergence. |
| Spatie Permission mandatory | **Optional.** Current custom RBAC works; migrate only with a clear gain. Prefer one permission engine package with a stable `AccessGate` Interface. |
| Filament for everything | **Not required.** Livewire + design system is the standard; Filament may be used inside Control Center later if it accelerates admin UX — as an implementation detail, not architecture law. |

---

# 4. Design Decisions (ADR Summary)

Formal ADRs live in `docs/adr/`. Critical decisions:

### ADR-001 — Database per company
**Decision:** Physical PostgreSQL database per company.  
**Why:** Strong isolation, simpler backups/restore per customer, matches current production.  
**Tradeoff:** More ops complexity than schema-per-tenant; mitigated by automation in Control Center.

### ADR-002 — Modular monolith, not microservices
**Decision:** One deployable per *product host* + shared packages; Control Center separate deployable.  
**Why:** Team size, transactional consistency inside a tenant, Laravel strength.  
**Revisit when:** Notification throughput or search needs independent scaling.

### ADR-003 — Composer packages as modules
**Decision:** Keep path packages + `ModuleLifecycle` install/uninstall.  
**Why:** Already proven in production; aligns with package-first goal.

### ADR-004 — Dual identity realms
**Decision:** Platform users (Control Center) ≠ Company users (tenant DB).  
**Why:** Different trust boundaries; prevents accidental privilege bleed.

### ADR-005 — Platform CRM ≠ Tenant CRM
**Decision:** Control Center CRM manages **Bproo’s sales pipeline** (prospects → companies → subscriptions). Tenant `crm` package manages **the company’s customers**.  
**Why:** Mixing them violates P3 and confuses language.

### ADR-006 — POS is a capability, not a separate database product
**Decision:** POS = enabled `sales` + `cash-register` (+ optional hardware integrations).  
**Why:** Same company, same stock, same users; separate product SKU is commercial, not architectural.

### ADR-007 — Laravel version target
**Decision:** Target **Laravel 12+** on the platform track; migrate apps incrementally (10 → 11 → 12).  
**Why:** Long-term support and ecosystem. Do not leapfrog without test gates.

### ADR-008 — Events for cross-context effects
**Decision:** Stock updates, notifications, audit, reporting projections listen to domain events.  
**Why:** Keeps packages decoupled; enables async via queues.

### ADR-009 — BAT financial documents stay vertical initially
**Decision:** `bat-invoicing` / `bat-purchasing` remain separate from retail `invoicing` / `purchases`.  
**Why:** Divergent schemas today; forced merge is a rewrite risk. Share Interfaces first.

### ADR-010 — Naming: Company (platform) vs Tenant (runtime)
**Decision:** Control Center entity = **Company**. Runtime connection context = **Tenant** (implementation detail). Product UI may say “Entreprise”.  
**Why:** Clearer sales language; keep `tenant` in code for connection middleware compatibility during migration.

---

# 5. System Context

```
                    ┌──────────────────────┐
                    │   Public Internet    │
                    └──────────┬───────────┘
           ┌───────────────────┼───────────────────┐
           ▼                   ▼                   ▼
   ┌───────────────┐   ┌───────────────┐   ┌───────────────┐
   │ Takoly /      │   │ Mobile Apps   │   │ Product Hosts │
   │ MaxiPanier    │   │               │   │ ERP Pressing  │
   └───────┬───────┘   └───────┬───────┘   │ BAT Hotel …   │
           │                   │           └───────┬───────┘
           │              OAuth2 / API              │
           └───────────────────┬───────────────────┘
                               ▼
                    ┌──────────────────────┐
                    │  Bproo Control Center │
                    │  (platform brain)     │
                    └──────────┬───────────┘
                               │ provisions / licenses / feature flags
                               ▼
                    ┌──────────────────────┐
                    │  Control Center DB    │
                    │  (platform data only) │
                    └──────────────────────┘

   Per company (business data):
   ┌────────────┐  ┌────────────┐  ┌────────────┐
   │ company_a  │  │ company_b  │  │ company_c  │
   │ PostgreSQL │  │ PostgreSQL │  │ PostgreSQL │
   └────────────┘  └────────────┘  └────────────┘
```

### Logical environments

```
Developer laptop → CI → Staging → Production
                     │
                     ├─ Control Center
                     ├─ Product hosts (N)
                     ├─ Redis / Queues
                     ├─ Object storage
                     └─ Observability stack
```

---

# 6. Multi-Tenancy Model

## 6.1 Pattern: Database per Company

```
Control Center DB
       │
       ├── creates credentials + database_company_{code}
       ├── stores connection metadata (encrypted)
       ├── assigns product licenses + modules
       └── does NOT store sales, stock, chantiers, pressing orders
```

## 6.2 What lives where

### Control Center database (ONLY)

Companies · Subscriptions · Licenses · Plans · Platform Users · Auth · Products (Bproo catalog) · Modules catalogue · Billing · Platform Invoices · Platform Payments · Platform CRM/Prospects · Notifications (platform) · Domains · API Keys · Feature Flags · Support Tickets · Deployment metadata · Backups metadata · Audit of platform actions

### Company database (tenant)

All business data for that company: users/roles (company), customers, catalogue, stock, sales, pressing orders, projets, etc.

## 6.3 Runtime resolution

Order of tenant resolution (configurable):

1. `X-Company-Code` / `X-Tenant-Code` header (API)
2. Query `?company=` / `?tenant=` (transition period: support both)
3. Subdomain `{company}.app.bproo.tld`
4. Custom domain → lookup in Control Center `domains` table

Middleware:

- `ResolveCompanyContext`
- `SetTenantConnection` (configures `database.connections.tenant`)
- `EnsureCompanyActive` / subscription gate
- `EnsureModuleEnabled` (`module:{key}`)
- `SetCurrentStore` (retail multi-store, optional)

## 6.4 Scalability notes

| Concern | Approach |
|---|---|
| App servers | Stateless; horizontal scale behind load balancer |
| Queues | Redis; workers scale independently; tenant-aware jobs serialize `company_code` |
| DB | One DB per company; heavy companies can move to dedicated Postgres instances |
| Cache | Redis keys prefixed `company:{code}:` |
| Files | Object storage prefix `companies/{code}/` |

## 6.5 Challenged alternative: schema-per-tenant

**Rejected for v1** (would require rewriting isolation and backup model). May revisit for tiny free-tier tenants later via a **different** product SKU — not by breaking existing customers.

---

# 7. Bproo Control Center

The Control Center is a **first-class application** (`apps/control-center`), replacing the duplicated `/admin` portals inside ERP/Pressing/BAT over time.

## 7.1 Mission

Be the **operating system** of the Bproo ecosystem: who the company is, what they bought, what is deployed, whether it is healthy, and how Bproo sells/supports them.

## 7.2 Feature catalogue

### 7.2.1 Companies
- Create / suspend / reactivate company
- Code, legal name, country, timezone, currency defaults
- Provisioning status machine: `pending → provisioning → ready | failed`
- Database host assignment, encrypted credentials
- Linked products (ERP, Pressing, BAT, …)
- Domains (subdomain + custom)
- Feature flags overrides
- Health dashboard (DB reachable, queue lag, last login)

### 7.2.2 Platform CRM & Sales Pipeline
- Prospects (leads for Bproo sales)
- Pipeline stages (new → qualified → proposal → won/lost)
- Activities / notes / tasks for sales reps
- Conversion: Prospect → Company (+ optional trial subscription)
- **Not** the tenant’s customer list

### 7.2.3 Clients (platform sense)
- Paying organizations / billing profiles
- Contacts for invoicing and support
- Linked to Company records

### 7.2.4 Products
- Bproo product catalogue: ERP, POS add-on, Pressing, BAT, Hotel, …
- Each product defines **default module bundle**
- Commercial metadata (name, SKU, description)

### 7.2.5 Modules
- Global module registry (key, version, compatibility, lifecycle handler FQCN)
- Per-company enablement
- Install / uninstall jobs against company DB
- Marketplace (internal + future third-party)
- Version compatibility matrix

### 7.2.6 Subscriptions, Plans, Licenses
- Plans (Free, Starter, Pro, Enterprise, custom)
- Subscription periods, grace, suspension
- Licenses: product × company × seats/modules
- Entitlement checks consumed by product hosts

### 7.2.7 Billing (platform)
- Platform invoices to companies
- Payments (mobile money, card, bank)
- Balance / wallet model (as in current ERP/Pressing) optional per market
- Dunning / reminders
- Tax configuration for Bproo’s own invoices

### 7.2.8 Deployments & Infrastructure
- Which app version a company runs (for gradual rollout)
- Region / cluster assignment
- Database server pool
- Backup schedule & last backup status
- Maintenance windows

### 7.2.9 Domains
- Map hostnames → company
- SSL status (if managed)
- Redirect rules

### 7.2.10 Backups
- Trigger / schedule tenant DB backups
- Retention policy
- Restore workflow (staging first)
- Metadata only in Control Center; blobs in backup storage

### 7.2.11 Monitoring
- Company health checks
- Error budget / uptime
- Queue depth per company (sampled)
- Integration with APM (Sentry/OpenTelemetry)

### 7.2.12 Notifications (platform)
- Email/SMS/WhatsApp to company admins (billing, suspension, provisioning)
- In-app Control Center notifications for staff

### 7.2.13 Support
- Support tickets (company-scoped)
- Internal notes, SLA clocks
- Link to company health + recent deploys

### 7.2.14 Logs & Audit
- Platform audit: who changed plan, who enabled module, who restored backup
- Access to aggregated app logs via correlation `company_code`

### 7.2.15 API Keys & Integrations
- Issuing platform API keys for public apps / partners
- OAuth clients for mobile
- Webhook endpoints configuration

### 7.2.16 Users & Permissions (platform staff)
- Control Center operators: Sales, Support, Billing, DevOps, SuperAdmin
- RBAC separate from tenant RBAC
- Future SSO (Google/Microsoft) for staff

### 7.2.17 Analytics
- MRR, churn, module adoption, provisioning success rate
- Product funnel from prospects

### 7.2.18 Feature Flags
- Global and per-company flags
- Percentage rollout
- Consumed by product hosts via cached API or signed JWT entitlements

### 7.2.19 Marketplace
- Browse modules
- Install to company (permissioned)
- Future: paid modules, third-party publishers

## 7.3 Control Center bounded contexts (packages)

Prefer composing Control Center from platform packages:

`tenancy` · `billing` · `modules` · `admin` (evolves into CC UI) · `platform-crm` · `support` · `feature-flags` · `deployments` · `audit` · `auth` · `permissions`

## 7.4 Migration from today’s Admin

| Today | Tomorrow |
|---|---|
| `/admin` inside each product | `apps/control-center` single URL |
| Duplicated Livewire Admin | One Admin/CC codebase |
| Per-app Plan models | Shared billing in CC DB |

**Transition:** Product hosts keep a thin `/admin` proxy or read-only link to CC until cutover. Do not big-bang remove Admin until CC reaches feature parity for that product.

---

# 8. Product Assembly Model

## 8.1 Principle

A **Product** is a commercial + technical bundle:

```
Product ERP = host apps/erp
            + module set {users, permissions, crm, catalogue, inventory,
                          sales, cash-register, purchases, …}
            + theme erp
```

```
Product Pressing = host apps/pressing
                 + shared subset
                 + vertical pressing
```

```
Product BAT = host apps/bat
            + shared foundation
            + vertical bat-*
```

## 8.2 POS

POS is **not** a separate host by default.

- Commercial SKU: “POS”
- Technical: `sales` + `cash-register` (+ hardware integration package later)
- May later offer `apps/pos` kiosk host that only mounts POS routes — same packages

## 8.3 Enabling modules

`config/modules.php` (per host) + Control Center `company_modules` decide runtime enablement.

Company cannot enable a module not licensed for its product without CC entitlement.

## 8.4 Future products (illustrative bundles)

| Product | Shared core | Vertical packages |
|---|---|---|
| Hotel | crm, catalogue, payments, calendar, media | `hotel-pms`, `hotel-reservations` |
| School | crm, hr, calendar, media, payments | `school-sis`, `school-fees` |
| Clinic | crm, catalogue, calendar, media | `clinic-emr`, `clinic-appointments` |
| Restaurant | catalogue, inventory, sales, cash-register | `restaurant-floor`, `restaurant-kds` |
| Pharmacy | catalogue, inventory, batches, sales, prescriptions | (mostly shared already) |
| E-Commerce | catalogue, inventory, payments, crm | `commerce-storefront-api` |
| Manufacturing | catalogue, inventory, purchases | `mfg-bom`, `mfg-mrp` |

---

# 9. Monorepo & Folder Structure

```
bproo-platform/
├── apps/
│   ├── control-center/          # Bproo Control Center (platform brain)
│   ├── erp/                     # ERP (+ POS capability)
│   ├── pressing/
│   ├── bat/
│   ├── pos/                     # Optional dedicated POS kiosk host (later)
│   ├── hotel/                   # Future
│   └── ...
├── packages/
│   ├── platform/                # Control-plane & cross-cutting
│   ├── shared/                  # Business bounded contexts
│   ├── verticals/               # Product-specific
│   └── ui/                      # Design system, Livewire primitives
├── shared/                      # Non-PHP shared assets (optional)
│   ├── proto/                   # Future API contracts
│   └── openapi/
├── bootstrap/                   # Monorepo tooling scripts
├── deployment/
│   ├── kubernetes/
│   ├── helm/ or compose/
│   └── environments/
├── docker/
├── tools/
│   ├── smoke/
│   ├── fingerprint/
│   └── codemods/
├── docs/
│   ├── adr/
│   ├── runbooks/
│   └── architecture/
├── .github/workflows/           # or GitLab CI
├── composer.json                # path repos workspace
└── README.md
```

### Folder responsibilities

| Path | Responsibility |
|---|---|
| `apps/*` | Composition root: env, `modules.php`, theme, thin routes, providers registration |
| `packages/platform/*` | Tenancy, auth, billing, modules, audit, … |
| `packages/shared/*` | Reusable business domains |
| `packages/verticals/*` | Single-product domains |
| `packages/ui/*` | Reusable Livewire/Blade/charts |
| `deployment/` | How we ship |
| `tools/` | Developer automation |
| `docs/` | Architecture, ADRs, runbooks |

**Rule:** If a class is needed by two products, it does not belong in `apps/`.

---

# 10. Package Architecture

## 10.1 Layers & dependency rule

```
apps → verticals → shared → platform → (illuminate/*, external libs)
ui may be used by apps/shared/verticals but must not own domain rules
```

**Forbidden dependencies:** `shared` → `verticals`; `platform` → `shared`/`verticals`.

## 10.2 Standard package skeleton

```
packages/{layer}/{name}/
├── composer.json
├── README.md
├── config/bproo/{name}.php
├── database/migrations/
├── resources/views/
├── routes/                          # optional; may register in provider
├── src/
│   ├── {Name}ServiceProvider.php
│   ├── {Name}Module.php             # ModuleLifecycle
│   ├── Models/
│   ├── Enums/
│   ├── Data/                        # DTOs
│   ├── Actions/                     # write use-cases
│   ├── Queries/                     # read use-cases (CQRS-lite)
│   ├── Services/                    # domain services
│   ├── Contracts/                   # Interfaces (if owned here)
│   ├── Events/
│   ├── Listeners/
│   ├── Policies/                    # only when valuable
│   ├── Http/
│   │   ├── Controllers/             # print/API
│   │   └── Livewire/
│   ├── Menus/
│   └── Widgets/
└── tests/
    ├── Unit/
    └── Feature/
```

## 10.3 Package catalogue (v1)

### Platform

| Package | Purpose (one line) |
|---|---|
| `core` | Contracts, TenantModel, casts, LazyModuleBoot |
| `tenancy` | Company/tenant connection, provisioning hooks |
| `auth` | Login/logout/password — platform & company guards |
| `users` | Company user identity |
| `permissions` | Roles, permissions, AccessGate |
| `settings` | Company settings & branding façade |
| `modules` | Module registry & install orchestration |
| `billing` | SaaS plans, subscriptions, platform payments |
| `companies` | Company aggregate helpers shared with CC |
| `feature-flags` | Flag evaluation |
| `admin` / CC UI | Operator UI building blocks |
| `platform-crm` | Bproo sales prospects/pipeline |
| `support` | Support tickets |
| `audit` | Security/compliance audit |
| `activity-log` | Entity activity timeline |
| `workflow` | Generic state machine |
| `notifications` | Multi-channel dispatcher |
| `mail` | Mail transport adapters (optional thin) |
| `sms` | SMS transport adapters |
| `whatsapp` | WhatsApp transport adapters |
| `media` | Files & attachments |
| `documents` | Generated business docs metadata (optional) |
| `printing` | PDF helpers |
| `stores` | Multi-store context |
| `api` | API auth scaffolding, versioning |
| `jobs` | Base tenant-aware job classes |
| `backup` | Backup trigger clients |
| `localization` | i18n helpers beyond Laravel lang |
| `integrations` | Third-party connector framework |
| `search` | Search index abstraction (Scout/Meilisearch) |
| `reporting` | Cross-module read reporting kernel |
| `dashboard` | Dashboard widget host |
| `calendar` | Calendar primitives |
| `files` | Low-level filesystem abstraction if needed |

> **CTO note on nano-packages:** `mail` / `sms` / `whatsapp` should start as **channels inside `notifications`**. Split only when a channel’s release cadence or credentials management diverges. Over-splitting is a maintainability smell.

### Shared business

`crm` · `catalogue` · `inventory` · `inventory-count` · `suppliers` · `purchases` · `sales` · `cash-register` · `quotations` · `invoicing` · `payments` · `debts` · `expenses` · `losses` · `returns` · `reservations` · `hr` · `attendance` · `tickets` · `batches` · `prescriptions` · `accounting` (future GL) · `manufacturing` (future)

### Verticals

`pressing` · `bat-offers` · `bat-quotes` · `bat-projects` · `bat-maintenance` · `bat-planning` · `bat-site-tracking` · `bat-logistics` · `bat-purchasing` · `bat-invoicing` · (future `hotel-*`, `school-*`, …)

### UI

`ui-core` · `ui-forms` · `ui-tables` · `ui-charts` · `ui-themes`

Detailed per-package specs: [Appendix §23](#23-appendix--package-catalogue-detail).

---

# 11. Domain Model

## 11.1 Platform (Control Center) domain

```
Plan 1──* Subscription *──1 Company 1──* License
                              │
                              ├──* CompanyDomain
                              ├──* CompanyModule (enabled modules)
                              ├──* FeatureFlagOverride
                              ├──* PlatformInvoice 1──* PlatformPayment
                              ├──* ApiKey
                              ├──* SupportTicket
                              ├──* DeploymentRecord
                              └──* BackupRecord

Prospect ──converts──► Company
PipelineActivity *──1 Prospect

PlatformUser *──* PlatformRole *──* PlatformPermission
```

**Company** fields (conceptual): code, name, status, region, db_name, db_host, encrypted secrets, product SKUs, provisioning_status, timestamps.

**Invariant:** No `Sale`, `StockLevel`, `PressingOrder`, or `Project` rows in Control Center DB.

## 11.2 Company (tenant) domain — shared

```
User *──* Role *──* Permission

Customer (Client) 1──* Address/Contact
Customer 1──* Sale / Invoice / Debt / Reservation …

Item (Product) *──* Category/Brand/Unit
Item 1──* StockLevel *──1 StorageLocation
Item ── Batch (pharmacy)

Supplier 1──* PurchaseOrder 1──* Receipt

Sale 1──* SaleLine
Sale 1──* TenderPayment
CaisseSession 1──* CaisseEntry

Invoice 1──* InvoiceLine
Invoice 1──* InvoicePayment

Employee 1──* PayrollLine / Leave / AttendancePunch
```

## 11.3 Vertical highlights

**Pressing:** `Agence` · `PressingOrder` · pieces · stages · `PressingDelivery` · loyalty  

**BAT:** `Offer` → `Quote` → `Project` / `MaintenanceContract` → `Invoice` · `SiteReport` · `Delivery`

## 11.4 Relationship to “Tenant”

`Tenant` in runtime code = connection context for `Company`. Prefer gradually renaming externally to Company while keeping `tenant` DB connection name for compatibility.

---

# 12. Application Runtime Architecture

```
┌──────────── HTTP / Livewire ────────────┐
│  Middleware: company resolve → DB connect│
│  → auth → module → store → localize      │
├──────────────────────────────────────────┤
│  Livewire Components / Controllers       │
│         │                                │
│         ▼                                │
│  Actions (commands) / Queries            │
│         │                                │
│         ▼                                │
│  Domain Services + Eloquent Aggregates   │
│         │                                │
│         ├── Domain Events → Queue        │
│         │         ▼                      │
│         │   Listeners (stock, notify…)   │
│         ▼                                │
│  PostgreSQL (company DB) / Redis / S3    │
└──────────────────────────────────────────┘
```

### Technology baseline (target)

| Concern | Choice |
|---|---|
| Framework | Laravel **12+** (migrate from 10) |
| PHP | 8.3+ |
| UI | Livewire 3/4 + Blade + `packages/ui` |
| DB | PostgreSQL 15+ |
| Cache / queue / session | Redis |
| PDF | DomPDF or licensed engine behind `printing` |
| Search | Meilisearch/Typesense via `search` (optional) |
| Auth API | Sanctum + future Passport/OAuth2 for public apps |

---

# 13. Shared UI System

## 13.1 Goals

One look-and-feel kit so new verticals do not reinvent tables/forms. Themes adapt per product brand without forking components.

## 13.2 Primitive catalogue

| Category | Components |
|---|---|
| Layouts | `AppShell`, `AuthLayout`, `PrintLayout`, `ControlCenterShell` |
| Navigation | `Sidebar`, `Topbar`, `ModuleMenu`, `StoreSwitcher` |
| Tables | `DataTable`, `Column`, `Pagination`, `BulkActions` |
| Forms | `TextField`, `Select`, `MoneyInput`, `DateTime`, `Repeater` |
| Feedback | `Toast`, `Dialog`, `Confirm`, `Alert` |
| Data display | `StatCard`, `EmptyState`, `DescriptionList` |
| Charts | `LineChart`, `BarChart`, `Donut` (wrapper over chart lib) |
| Widgets | Dashboard widget contract `Widget` |
| Icons | SVG sprite / consistent icon component |
| Themes | CSS variables: `--brand-primary`, density, radius |

## 13.3 Rules

- Domain packages **compose** UI primitives; they do not fork CSS frameworks per package.
- Product hosts select a **theme package** (`ui-themes/erp`, `ui-themes/pressing`).
- No business calculations inside UI packages.

---

# 14. Event-Driven Design & CQRS

## 14.1 Domain events (examples)

| Event | Publisher | Typical consumers |
|---|---|---|
| `SaleCompleted` | sales | inventory, cash-register, reporting, notifications |
| `StockMoved` | inventory | reporting, audit |
| `InvoiceIssued` | invoicing | payments, notifications |
| `QuotationAccepted` | quotations / bat-quotes | projects, invoicing |
| `PressingOrderReady` | pressing | notifications (WhatsApp/SMS) |
| `ModuleInstalled` | modules | audit, analytics |
| `CompanyProvisioned` | tenancy | notifications, CRM |

## 14.2 Delivery

- Sync listeners for **same-aggregate consistency** (careful, keep tiny)
- Queued listeners for notifications, search indexing, analytics
- Tenant-aware: job payload includes `company_code`; worker sets connection first

## 14.3 CQRS (selective)

| Use CQRS | Do not use CQRS |
|---|---|
| Reporting dashboards | Simple entity CRUD forms |
| Search indexes | Settings screens |
| Control Center analytics | Most Livewire Modals |
| Cross-company metrics (CC only) | In-tenant operational screens |

**Pattern:** `Actions` (writes) + `Queries` (reads). Queries may use Eloquent or read models/SQL views.

## 14.4 Repository pattern (selective)

Use repositories when:

- Query logic is reused across Actions and Queries heavily
- You need an in-memory fake for unit tests of a complex domain service
- Read model is not Eloquent (e.g. ClickHouse later)

Do **not** create `UserRepository` that only wraps `User::query()`.

---

# 15. Coding Standards

## 15.1 Naming

| Artifact | Convention |
|---|---|
| Package folder | kebab-case: `cash-register` |
| Composer name | `bproo/cash-register` |
| Namespace | `Bproo\Shared\CashRegister\` |
| Models | Singular PascalCase: `SaleLine` |
| Actions | Verb phrase: `CompleteSale` |
| Queries | `ListSales`, `GetSaleDashboardStats` |
| Events | Past tense: `SaleCompleted` |
| Listeners | `DeductStockOnSaleCompleted` |
| Policies | `SalePolicy` |
| Permissions | `{module}.{action}`: `sales.create` |
| Module keys | snake: `cash_register` or keep legacy `caisse` during migration |
| Routes | `tenant.sales.index` |
| Migrations | `2026_07_28_000001_create_sales_table` |

## 15.2 Namespaces

```
Bproo\Platform\{Package}\...
Bproo\Shared\{Package}\...
Bproo\Verticals\{Package}\...
Bproo\Ui\{Package}\...
```

During migration, `InovCom\*` aliases remain until Phase rename complete.

## 15.3 Migrations

- Tagged per package: `bproo-{package}-migrations`
- Tenant migrations never run on Control Center connection
- Additive + expandable; avoid destructive changes without expand/contract
- No cross-package foreign keys to tables you don’t own — use soft IDs

## 15.4 Routes

- Product UI under `/app`
- Control Center under `/` or `/cc` (decide one; document in ADR)
- API under `/api/v1`
- Module middleware on every feature route

## 15.5 Services vs Actions

- **Action:** one use-case, invokable, thin transaction boundary  
- **Service:** multi-step domain capability reused by several Actions  
- Prefer Actions in new code for clarity

## 15.6 DTOs

- `Data` namespace; readonly PHP classes or spatie/laravel-data if adopted globally
- API boundaries and Action inputs use DTOs; Livewire may map to arrays carefully

## 15.7 Validation

- Form Request or Action-level Validator
- Reuse Rule objects in package `Rules/`
- Money: integer minors **or** carefully scaled decimals with single cast (`TrimmedDecimal`) — pick one platform standard in ADR-011

## 15.8 Testing

| Level | Requirement |
|---|---|
| Package unit | Domain services / Actions with fakes |
| Package feature | Livewire smoke + HTTP print endpoints |
| App smoke | Runbooks per product |
| Contract | Interface implementations conform |

## 15.9 Git commits

```
feat(sales): allow split mobile-money tender
fix(pressing): block delivery without settlement
chore(platform): extract TenantManager
docs(architecture): add ADR-012
```

## 15.10 Versioning & release

- Packages: semver; host apps pin ranges
- Module `version` field in CC registry
- Release train: weekly staging, bi-weekly production for platform; verticals can ship faster if isolated
- Changelog per package

---

# 16. Security Architecture

## 16.1 Authentication

| Realm | Guard | Store |
|---|---|---|
| Control Center staff | `web` / `cc` | Control Center DB |
| Company users | `tenant` | Company DB |
| Public APIs | Sanctum tokens / OAuth2 | CC for clients; company for end users as designed |

Password hashing: Argon2id/bcrypt (Laravel default).  
MFA: roadmap for CC staff first, then company admins.

## 16.2 Authorization

- Permission keys via `AccessGate`
- Policies optional for complex resource rules (e.g. “only assignee”)
- Module middleware as coarse gate
- Admin role short-circuit must be explicit and audited

## 16.3 API security

- Token scopes
- Rate limiting per token and per IP (Redis)
- Signed webhooks with rotation
- Future SSO: OIDC for staff; optional SAML for enterprise companies

## 16.4 Data isolation

- No shared business tables across companies
- Encrypted DB passwords at rest in CC
- Object storage prefixes enforced in `media`
- Queue jobs must re-resolve company; never reuse leftover connection state

## 16.5 Secrets

- Env + secret manager (Doppler/Vault/AWS SM) in production
- Never commit `.env`
- Rotate API keys via CC UI

## 16.6 Audit & encryption

- `audit` package for authz changes, billing, module install, backup restore
- Field encryption for PII where legally required
- TLS everywhere

## 16.7 Backups

- Per-company DB backups automated
- Periodic restore drills on staging
- Backup access audited

---

# 17. Deployment & Operations

## 17.1 Environments

| Env | Purpose |
|---|---|
| Local | Sail/Docker; one CC DB + sample company DBs |
| CI | Automated tests + package fingerprint |
| Staging | Production-like; anonymized tenants |
| Production | Multi-node app; managed Postgres; Redis; object storage |

## 17.2 Docker

- Images per app (`control-center`, `erp`, `pressing`, `bat`)
- Shared base PHP image
- Separate containers: `web`, `queue`, `scheduler`

## 17.3 CI/CD

```
PR → lint/tests/fingerprint → build images → deploy staging → smoke
main/tag → production canary (one company cohort) → full rollout
```

## 17.4 Data stores

- PostgreSQL: CC cluster + tenant DB hosts (pool)
- Redis: cache, queues, rate limits
- S3-compatible: media + backups

## 17.5 Observability

- Structured JSON logs with `company_code`, `request_id`
- Metrics: latency, queue lag, provisioning failures
- Tracing optional (OpenTelemetry)
- Uptime checks on CC and product login

## 17.6 Queue workers

- Horizon (or equivalent) per product
- Prioritize interactive jobs vs bulk
- Tenant-aware middleware on jobs

---

# 18. Incremental Migration Strategy

Aligns with `MIGRATION_ROADMAP.md`. Summary for this handbook:

| Phase | Outcome | Production impact |
|---|---|---|
| 0 | Inventory, baselines, smoke | None |
| 1 | Monorepo, paths only | None |
| 2 | Share identical ERP↔Pressing packages | None if tests pass |
| 3 | Reconcile drifted packages | None |
| 4 | Extract platform (tenancy/admin/billing) | None (aliases) |
| 5 | Vertical folders | None |
| 6 | BAT on shared foundation | None |
| 7 | SOLID renames | None (aliases) |
| 8 | Cross-vertical contracts | None |
| 9 | Optional schema merge | Controlled windows |

### Extraction pattern (repeatable)

1. Identify package with high duplication / clear boundary  
2. Diff → choose canonical tree  
3. Move to `packages/...`  
4. Point both apps’ composer paths  
5. Keep old path as stub for one release if needed  
6. Smoke + pilot company  
7. Delete duplicate tree  

### Hard migration bans

- No rewrite of Pressing Kanban or BAT devis in platform phases  
- No renaming permission keys without dual-read period  
- No moving business tables into CC DB  

---

# 19. Risks & Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Over-modularization (100 nano-packages) | Slow delivery | Package review board; merge channels into notifications |
| Forcing BAT↔ERP schema unity early | Outages | Contracts first; M9 optional |
| Control Center feature lag vs old Admin | Ops pain | Parity checklist before decommission |
| Laravel 10→12 jump | Regressions | Stepwise upgrades with smoke gates |
| Connection bleed in queues | Data leak | Mandatory tenant job middleware + tests |
| Duplicate CRM language confusion | Wrong features built | Strict glossary (platform vs company CRM) |
| Single region Postgres limits | Scale pain | DB host pools in CC deployments |

---

# 20. Future Roadmap

| Horizon | Themes |
|---|---|
| **H1** | Monorepo, shared retail packages, Control Center MVP parity with Admin |
| **H2** | BAT foundation align; notifications channels; API for Takoly/MaxiPanier |
| **H3** | Hotel/School first verticals; OAuth2; search package |
| **H4** | Accounting GL; manufacturing; marketplace third-parties |
| **H5** | Optional multi-region; read replicas for heavy companies |

---

# 21. Best Practices Checklist

**Before merging a PR**

- [ ] Dependency direction respected  
- [ ] No business data written to CC DB  
- [ ] Permissions declared in Module lifecycle  
- [ ] Domain events for cross-package effects  
- [ ] Package tests added/updated  
- [ ] No new duplicate package tree  
- [ ] Route & permission keys stable or dual-supported  
- [ ] Tenant-aware jobs if queued  

**Before enabling a module in production**

- [ ] Licensed in Control Center  
- [ ] Migrations tested on clone  
- [ ] Smoke path documented  

---

# 22. Glossary

| Term | Meaning |
|---|---|
| **Company** | Customer organization; owns a business database |
| **Tenant** | Runtime DB connection context for a Company |
| **Control Center** | Platform brain application |
| **Product** | Commercial app (ERP, Pressing, …) |
| **Module** | Installable package capability licensed per company |
| **Vertical package** | Domain code for one product family |
| **Shared package** | Domain code reused across products |
| **Platform CRM** | Bproo’s own sales pipeline |
| **Company CRM** | End-customer management inside a company DB |
| **POS** | Sales capability + cash register; usually part of ERP |

---

# 23. Appendix — Package Catalogue Detail

> Template applied to every critical package. For brevity, sister packages follow the same skeleton; expand in package READMEs as they are extracted.

---

## 23.1 `packages/platform/core`

**Purpose:** Foundation contracts and base classes.  
**Responsibilities:** `TenantModel`, casts, `ModuleLifecycle`, shared Interfaces (`ItemsApi`, `ClientsApi`, `StockApi`, `BatchesApi`, `InvoicingApi`, `PurchasingApi`, `SettingsStore`).  
**Folder structure:** Standard without Http/Livewire.  
**Models:** abstract `TenantModel` only.  
**Services:** none.  
**Events / Listeners / Policies:** none.  
**Routes / Livewire / Views / Menus / Widgets:** none.  
**Interfaces:** see above.  
**Configuration:** `bproo/core.php`.  
**Database:** none.  
**Dependencies:** illuminate only.  
**Public APIs:** contracts + base model + `LazyModuleBoot`.  
**Permissions:** none.  
**Testing:** unit tests for casts; interface role tests.  
**Future:** more Interfaces as contexts stabilize.

---

## 23.2 `packages/platform/tenancy`

**Purpose:** Resolve company context and bind DB connection.  
**Responsibilities:** provisioning hooks, connection factory, company status gates.  
**Models:** uses CC `Company` (or local read model).  
**Services:** `TenantManager`, `TenantProvisioner`, `TenantConnectionFactory`.  
**Events:** `CompanyProvisioningStarted`, `CompanyProvisioned`, `CompanySuspended`.  
**Listeners:** notify, audit.  
**Policies:** none.  
**Routes:** none (middleware).  
**Livewire / Views:** none.  
**Interfaces:** `TenantResolver`, `TenantContext`.  
**Configuration:** resolution order, DB prefix.  
**Database:** none in tenant; relies on CC.  
**Dependencies:** `core`.  
**Public APIs:** middleware aliases, `TenantContext`.  
**Permissions:** none.  
**Menus/Widgets:** none.  
**Testing:** connection switching feature tests.  
**Future:** multi-region router.

---

## 23.3 `packages/platform/auth`

**Purpose:** Authentication only.  
**Responsibilities:** session login/logout, password reset; guard wiring.  
**Models:** none owned.  
**Services:** `AdminAuthService`, `CompanyAuthService`.  
**Events:** login success/failure.  
**Listeners:** audit.  
**Policies:** none.  
**Routes:** CC login; `/app/login`.  
**Livewire:** optional login forms.  
**Views:** auth layouts.  
**Interfaces:** `AuthenticatableUserProvider`.  
**Configuration:** guards, session.  
**Database:** password reset tables as needed.  
**Dependencies:** `core`, `users`, `tenancy`.  
**Public APIs:** guards.  
**Permissions:** none.  
**Testing:** login feature tests both realms.  
**Future:** MFA, OIDC.

---

## 23.4 `packages/platform/users`

**Purpose:** Company user identity.  
**Responsibilities:** CRUD users, active flag, store assignment fields.  
**Models:** `User`.  
**Services:** `UserService`, `UserDirectory`.  
**Events:** `UserCreated`, `UserDeactivated`.  
**Listeners:** none required.  
**Policies:** `UserPolicy` optional.  
**Routes:** `/app/users`.  
**Livewire:** `UsersIndex`, `UserForm`.  
**Views:** user blades.  
**Interfaces:** `UserDirectory`, `CurrentUser`.  
**Configuration:** invite rules.  
**Database:** `users`.  
**Dependencies:** `core`, `permissions`.  
**Public APIs:** `UserDirectory`.  
**Permissions:** `users.*`.  
**Menus:** Users.  
**Widgets:** none.  
**Testing:** CRUD + deactivate.  
**Future:** SCIM provisioning.

---

## 23.5 `packages/platform/permissions`

**Purpose:** RBAC engine.  
**Responsibilities:** roles, permissions, matrix UI, `AccessGate`.  
**Models:** `Role`, `Permission`.  
**Services:** `PermissionRegistrar`, `RoleService`, `AccessGate`, `PermissionCatalog`.  
**Events:** `PermissionsSynced`.  
**Listeners:** audit.  
**Policies:** none (meta).  
**Routes:** `/app/roles`, `/app/permissions`.  
**Livewire:** `RolesIndex`, `RoleForm`, `PermissionsMatrix`.  
**Views:** matrix blades.  
**Interfaces:** `AccessGate`, `PermissionRegistrar`.  
**Configuration:** admin role name, wildcards.  
**Database:** roles, permissions, pivots.  
**Dependencies:** `core`, `users`.  
**Public APIs:** `AccessGate::check`.  
**Permissions:** `roles.*`, `permissions.manage`.  
**Menus:** Roles & permissions.  
**Testing:** matrix + gate.  
**Future:** ABAC attributes if needed.

---

## 23.6 `packages/platform/settings`

**Purpose:** Typed company settings & branding.  
**Models:** façade over settings store.  
**Services:** `SettingsRepository`, `BrandingReader`.  
**Events:** `SettingsUpdated`.  
**Routes / Livewire:** settings hub, branding.  
**Interfaces:** `SettingsStore`.  
**Permissions:** `settings.*`, `branding.*`.  
**Dependencies:** `core`, `tenancy`.  
**Testing:** get/set roundtrip.  
**Future:** settings versioning.

---

## 23.7 `packages/platform/modules`

**Purpose:** Module catalogue & lifecycle orchestration.  
**Models:** `Module`, `CompanyModule`, `ModuleEvent` (CC).  
**Services:** `ModuleRegistry`, `ModuleMarketplace`, `ModuleVersionService`.  
**Events:** `ModuleInstalled`, `ModuleUninstalled`.  
**Interfaces:** `ModuleRegistryContract`.  
**Dependencies:** `core`, `tenancy`.  
**Public APIs:** `isEnabled`, `install`, middleware `module:{key}`.  
**Testing:** install/uninstall on ephemeral DB.  
**Future:** remote marketplace signing.

---

## 23.8 `packages/platform/billing`

**Purpose:** SaaS commercial entitlements (not tenant invoicing).  
**Models:** `Plan`, `Subscription`, `PlatformInvoice`, `PlatformPayment`, balance txs.  
**Services:** `SubscriptionService`, `EntitlementChecker`.  
**Events:** `SubscriptionSuspended`, `PaymentRecorded`.  
**Interfaces:** `BillingDriver`, `SubscriptionStatusReader`.  
**Dependencies:** `core`, `companies`.  
**Testing:** grace/suspend logic.  
**Future:** tax engines, multi-currency plans.

---

## 23.9 `packages/platform/companies`

**Purpose:** Company aggregate helpers shared by CC and tenancy.  
**Models:** `Company` (CC).  
**Services:** `CompanyService`, `CompanyCodeGenerator`.  
**Events:** `CompanyCreated`.  
**Dependencies:** `core`.  
**Testing:** code uniqueness.  
**Future:** groups / holdings.

---

## 23.10 `packages/platform/notifications` (+ channels)

**Purpose:** Dispatch messages across channels.  
**Models:** templates, logs, preferences.  
**Services:** `NotificationDispatcher`, channel drivers.  
**Events:** `NotificationDispatched`, `NotificationFailed`.  
**Interfaces:** `NotificationChannel`.  
**Livewire:** bell, settings.  
**Dependencies:** `core`, `users`, `settings`.  
**Permissions:** `notifications.manage`.  
**Testing:** fake HTTP for WhatsApp/SMS.  
**Future:** split `sms`/`whatsapp` packages if needed; prefer channels first.  
**Extensions:** email digests, push.

---

## 23.11 `packages/platform/workflow`

**Purpose:** Reusable state machine.  
**Services:** `StateMachine`.  
**Events:** `WorkflowTransitioned`.  
**Interfaces:** `Workflowable`, `TransitionMap`.  
**Dependencies:** `core`, optional `activity-log`.  
**Testing:** illegal transition throws.  
**Future:** visual workflow designer (CC).

---

## 23.12 `packages/platform/audit` & `activity-log`

**Audit:** security-grade immutable log (`Auditor`).  
**Activity-log:** polymorphic timeline for entities.  
**Do not merge** — different retention and intent.  
**Permissions:** `audit.view`.  
**Testing:** recording + redaction of secrets.

---

## 23.13 `packages/platform/media` & `printing`

**Media:** upload, attach, download, versioning.  
**Printing:** PDF renderer, tax layout helpers, amount-in-words.  
**Dependencies:** storage disk; DomPDF.  
**Permissions:** `media.*`.  
**Testing:** upload fake disk; PDF smoke.

---

## 23.14 `packages/platform/api` · `jobs` · `backup` · `feature-flags` · `search` · `localization` · `integrations` · `calendar` · `dashboard` · `reporting` (kernel)

| Package | Purpose |
|---|---|
| `api` | Versioned API scaffolding, auth middleware, OpenAPI hooks |
| `jobs` | `TenantJob` base, rate-limited batches |
| `backup` | Client to trigger/monitor backups |
| `feature-flags` | Evaluate flags from CC cache |
| `search` | Index abstraction |
| `localization` | Locale helpers, date/money formatting |
| `integrations` | Connector SDK (webhooks out/in) |
| `calendar` | Appointment primitives shared by BAT/Hotel/Clinic |
| `dashboard` | Widget discovery host |
| `reporting` | Report datasource interfaces + exporters |

Each follows the standard skeleton; keep them thin.

---

## 23.15 `packages/shared/crm`

**Purpose:** Company customers & leads.  
**Responsibilities:** clients, contacts, segments, credit, prospects.  
**Models:** Client, Contact, Address, Segment, Prospect, …  
**Services:** `ClientsApiService`, credit/aging/duplicates, `ProspectsService`.  
**Events:** `ClientCreated`, `ProspectConverted`.  
**Interfaces:** `ClientsApi`.  
**Livewire:** index/form/360/prospects.  
**Permissions:** `clients.*`, `prospects.*`.  
**Menus:** CRM group.  
**Dependencies:** `core`, `permissions`, optional `media`, `activity-log`.  
**Testing:** credit limit rules; conversion.  
**Future:** marketing lists.

---

## 23.16 `packages/shared/catalogue`

**Purpose:** Items/products master data (not stock qty).  
**Models:** Item, Category, Brand, Unit, prices, sets.  
**Services:** `ItemsApiService`.  
**Events:** `ItemCreated/Updated/Deleted`.  
**Interfaces:** `ItemsApi`.  
**Permissions:** `items.*`.  
**Dependencies:** `core`.  
**Future:** variants, barcode packs.

---

## 23.17 `packages/shared/inventory` & `inventory-count`

**Inventory:** levels, movements, locations, transfers — `StockApi`.  
**Inventory-count:** stocktake sessions; emits events → `StockApi`.  
**Never mix** perpetual engine with stocktake UX in one package.  
**Permissions:** `stock.*`, `inventory.*`.  
**Testing:** concurrent movement guards.

---

## 23.18 `packages/shared/sales` & `cash-register` (POS)

**Sales:** checkout aggregate, returns, suspended sales.  
**Cash-register:** till sessions & ledger.  
**Events:** `SaleCompleted` → stock + caisse listeners.  
**Permissions:** `sales.*`, `caisse.*` / `cash_register.*` (alias during migration).  
**Menus:** POS, Sales history, Caisse.  
**Widgets:** today’s turnover.  
**Testing:** split tender; park/resume; stock deduction.  
**Future:** offline kiosk sync, fiscal printers.

---

## 23.19 `packages/shared/purchases` & `suppliers`

**Suppliers:** master data.  
**Purchases:** PO + receipts + FX purchases; posts via `StockApi`.  
**Interfaces:** `SuppliersApi`, `PurchasingApi`.  
**Testing:** receive updates stock and price history.

---

## 23.20 `packages/shared/quotations` · `invoicing` · `payments` · `debts` · `returns` · `reservations`

Separate packages along document boundaries:

| Package | Write responsibility |
|---|---|
| quotations | Quotes lifecycle |
| invoicing | Invoices, DN, reminders |
| payments | Allocating money to invoices |
| debts | Informal credit books |
| returns | RMA, credit notes, refunds |
| reservations | Holds on stock |

**CTO challenge:** Do not create a mega-`accounting` until you have a real GL chart of accounts. Today’s expenses/debts/invoicing are **operational finance**, not full accounting.

---

## 23.21 `packages/shared/hr` · `attendance` · `tickets` · `batches` · `prescriptions` · `expenses` · `losses`

Standard skeleton; pharmacy modules gated by product/tenant type; HR≠Attendance.

---

## 23.22 `packages/shared/accounting` (future)

**Purpose:** Double-entry GL, journals, tax periods.  
**Status:** Placeholder — do not dump operational invoices here prematurely.  
**When:** After invoicing/payments stabilize and accountants demand GL.

---

## 23.23 Vertical: `packages/verticals/pressing`

**Purpose:** Laundry operations.  
**Responsibilities:** agences, orders, tri, kanban, delivery, loyalty, consumables.  
**Dependencies:** `core`, `catalogue`, `inventory`, `notifications`, `workflow`, `printing`.  
**Menus:** Pressing group.  
**Widgets:** overdue orders, production queue.  
**Testing:** credit gate on delivery; stage transitions.  
**Future:** route optimization for drivers.

---

## 23.24 Verticals: `bat-*`

Each BAT package = one bounded context (offers, quotes, projects, maintenance, planning, site-tracking, logistics, purchasing, invoicing).  
**Dependencies:** shared foundation + `workflow` + `media` + `calendar`.  
**Anti-corruption:** do not import retail `invoicing` models.  
**Testing:** quote accept → project; SLA jobs; delivery→stock.  
**Future:** BIM/document heavy features via `media`.

---

## 23.25 `packages/ui/*`

**Purpose:** Design system only.  
**Responsibilities:** layouts, tables, forms, charts, themes.  
**Dependencies:** Livewire, Alpine if used; **no domain packages**.  
**Testing:** browser/component tests optional.  
**Future:** dark theme per product — via tokens, not forks.

---

# Closing — CTO Message

Bproo’s advantage is not a greenfield rewrite. It is that **modular Laravel packages and DB-per-company tenancy already exist in production**.

The winning move:

1. **Unify Control Center** so Admin stops triplicating.  
2. **Deduplicate ERP ↔ Pressing** until one package set remains.  
3. **Keep verticals vertical**.  
4. **Share contracts**, not fantasies of one universal schema.  
5. **Upgrade Laravel deliberately** toward 12+.  
6. **Assemble** Hotel/School/Clinic from packages — prove the 90% reuse thesis.

This handbook is the contract. Change it via ADRs — not via silent shortcuts.

---

**Document control**

| Version | Date | Notes |
|---|---|---|
| 1.0.0 | 2026-07-28 | Initial official architecture |

*End of Bproo Platform Architecture v1.0*
