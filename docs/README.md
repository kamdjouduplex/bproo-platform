# Bproo Platform — Documentation Index

Official architecture and migration docs for the Bproo ecosystem.

## Start here

| Doc | Purpose |
|---|---|
| [BPROO_PLATFORM_ARCHITECTURE_v1.md](./BPROO_PLATFORM_ARCHITECTURE_v1.md) | **Official** architecture handbook |
| [MIGRATION_ROADMAP.md](./MIGRATION_ROADMAP.md) | Incremental migration plan (v1.1) |
| [PHASE_0_STATUS.md](./PHASE_0_STATUS.md) | Phase 0 progress |
| [PHASE_1_STATUS.md](./PHASE_1_STATUS.md) | Phase 1 monorepo scaffold |
| [PHASE_2_STATUS.md](./PHASE_2_STATUS.md) | Phase 2 shared identical packages |
| [FREEZE_POLICY.md](./FREEZE_POLICY.md) | No-duplicate-packages rule (M0–M3) |

## Analysis (inputs)

| Doc | Purpose |
|---|---|
| [ARCHITECTURE_AUDIT.md](./ARCHITECTURE_AUDIT.md) | What exists in ERP / Pressing / BAT |
| [DUPLICATION_REPORT.md](./DUPLICATION_REPORT.md) | Duplication metrics & extraction priority |
| [IDEAL_MODULAR_ARCHITECTURE.md](./IDEAL_MODULAR_ARCHITECTURE.md) | Target package design detail |

## Process

| Path | Purpose |
|---|---|
| [adr/](./adr/) | Architecture Decision Records |
| [runbooks/](./runbooks/) | Smoke test checklists |
| [inventory/](./inventory/) | Company / module inventory |
| [offline/](./offline/) | Desktop / offline runtime epic (licence, updates, sync) |

Generated package-drift reports (if any) can be produced via `tools/fingerprint/compare-inovcom-packages.ps1` — they are not stored in the repo.

## Recent ADRs

| ADR | Title |
|---|---|
| [0001](./adr/0001-adopt-architecture-v1.md) | Adopt architecture v1 |
| [0002](./adr/0002-offline-desktop-runtime.md) | Offline desktop runtime (licence, updates, sync) |
