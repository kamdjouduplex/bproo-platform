# ADR 0002 — Offline desktop runtime (licence, updates, sync)

| Field | Value |
|---|---|
| **ADR** | 0002 |
| **Title** | Offline / stand-alone desktop as a first-class delivery mode beside SaaS |
| **Date** | 2026-09-15 |
| **Status** | Accepted |
| **Deciders** | Bproo / Afroinov product + engineering |
| **Branch** | `feature/offline-runtime` |

## Context

A large share of African customers (estimate ≥ 60 %) need Bproo to run **offline** as a stand-alone install (pharmacy / school / shop), similar to legacy desktop suites (e.g. Sage), while Afroinov must retain:

1. **Licence control** (activation, modules, expiry / revocation)
2. **Remote updates** (no on-site visits)
3. Ability to **scale the same product as SaaS** where connectivity is good

Rewriting the monorepo as a second desktop codebase would freeze feature velocity. SaaS work on `main` (School, Pharma, …) must continue in parallel.

## Decision

### 1. One product, two runtimes
- Keep the shared monorepo (`apps/*`, `packages/*`).
- **SaaS** = current multi-tenant cloud deploy.
- **Desktop** = packaging of the **same** vertical app as **single-tenant local** (local DB), not a rewrite.
- Epic work lives on branch **`feature/offline-runtime`**; day-to-day SaaS stays on **`main`**. Merge `main` → epic regularly.

### 2. Sync model (event-first)
- Syncable entities use stable **UUID/ULID** (in addition to existing integer PKs where needed).
- Domain changes that must survive offline are expressed as **append-only, idempotent events**, e.g. `sale.created`, `stock.adjusted`, `debt.payment_recorded`.
- Each install has:
  - **OUT** queue (local → cloud)
  - **IN** queue (cloud → local)
- Phase 1 sync goal = **backup / licence / updates**. Multi-store bidirectional sync is Phase 2+.
- Conflicts: LWW allowed on master data (items, clients) with audit; **never** blind LWW on stock/sales — replay events; surface reconciliation UI if needed.

### 3. Licence (Control Center)
- Activation online at first launch → signed licence token (`org` + soft machine fingerprint).
- Periodic heartbeat (e.g. every 7 days) when online.
- **Offline grace: 14–30 days** without contact; then **read-only** (not hard brick on day 1).
- Modules continue to use existing `tenant_modules` / permission model.
- Revocation and plan changes are enforced at next successful heartbeat (or at grace end).

### 4. Remote updates
- Signed **update manifest** hosted by Afroinov (e.g. `updates.afroinov.com`).
- Desktop downloads signed package (app + migrations), applies, health-checks, **rolls back** on failure.
- Same CI pipeline produces **SaaS image** and **desktop artefact** from one commit when possible.

### 5. Offline-critical scope (must work without network)
- POS / sales, cash register, stock movements, debts/payments, local catalogue.
- Cloud-only features (central BI, multi-site consolidation) degrade gracefully when offline.

### 6. Phased delivery
| Phase | Deliverable |
|------|-------------|
| 0 | This ADR + engineering checklist (UUID/events on new work) |
| 1 | Licence issue / heartbeat / grace in Control Center |
| 2 | Update channel (manifest + apply + rollback) — **done** (CC channel; runtime apply in Phase 4) |
| 3 | Local event bus + OUT sync (cloud backup) |
| 4 | Windows installer (single shop / school) |
| 5 | Full IN sync / multi-device (premium) |

## Consequences

### Positive
- Serves majority offline market without abandoning SaaS
- Afroinov keeps commercial control (licence + updates)
- One codebase → features land once for both modes
- School/Pharma delivery on `main` is not blocked

### Negative / risks
- Extra QA matrix (SaaS + Desktop)
- Legacy tables without UUID need migration strategy before deep sync
- Weak licence design could annoy customers *or* enable piracy — grace window is the compromise
- Packaging Windows/ops skill required

### Neutral
- Control Center becomes the control plane for desktop as well as SaaS tenants

## Alternatives considered

1. **Pure SaaS only** — Rejected: loses ~60 % addressable offline demand.
2. **Separate desktop rewrite (.NET / Electron-only)** — Rejected: doubles cost, splits roadmap.
3. **USB dongle licensing** — Rejected for v1: friction in field; soft licence + grace preferred.
4. **Always-online desktop thin client** — Rejected: fails the offline requirement.

## Compliance

- [x] Aligns with `BPROO_PLATFORM_ARCHITECTURE_v1.md` (shared packages, Control Center as brain, DB-per-company)
- [x] Does not replace tenancy model; desktop = one local company DB
- [x] Public SaaS contracts unchanged on `main` until desktop packages are explicitly released
- [x] Phase notes mirrored in `docs/offline/PHASE-1-LICENCE.md` (Phase 1 coding)

## Engineering checklist (from Phase 0)

When touching sync-bound domains on any branch:

1. Prefer UUID/ULID columns for new syncable rows.
2. Avoid “cloud HTTP inside the write path” without an offline queue fallback.
3. Keep module keys / permissions compatible with Control Center.
4. Document new event types in `docs/offline/` when introduced.
