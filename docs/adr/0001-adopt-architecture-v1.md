# ADR 0001 — Adopt Bproo Platform Architecture v1.0

| Field | Value |
|---|---|
| **ADR** | 0001 |
| **Title** | Adopt Bproo Platform Architecture v1.0 as official technical reference |
| **Date** | 2026-07-28 |
| **Status** | Accepted |
| **Deciders** | Bproo Dev — Architecture / CTO Office |

## Context

Bproo operates three production Laravel applications (ERP/POS, Pressing, BAT) with duplicated Admin/control planes and near-identical retail packages between ERP and Pressing. Future products (Hotel, School, Clinic, public apps) require a shared modular architecture without rewriting production systems.

Architecture documents were produced and placed under `docs/`:

- `BPROO_PLATFORM_ARCHITECTURE_v1.md` (north star)
- `MIGRATION_ROADMAP.md` v1.1
- `ARCHITECTURE_AUDIT.md`
- `DUPLICATION_REPORT.md`
- `IDEAL_MODULAR_ARCHITECTURE.md`

## Decision

1. **`BPROO_PLATFORM_ARCHITECTURE_v1.md` is the official architecture.** Silent divergence is forbidden; changes require a new ADR.
2. **Migration follows `MIGRATION_ROADMAP.md` v1.1**, starting at Phase 0.
3. **Database-per-company** remains the tenancy model.
4. **Control Center** will become the single ops brain; product `/admin` remains until Control Center MVP parity (Phase M4b).
5. **No rewrite** of vertical domains; extract/dedupe only.
6. **Laravel 12+** is the target via stepwise upgrades (parallel track), not a Phase 0–2 prerequisite.
7. **Freeze:** during Phases M1–M3, do not create new duplicate copies of `packages/inovcom/*` across apps. New shared work must target a single canonical tree once M2 begins.

## Consequences

### Positive
- Single technical reference for all engineers
- Clear start gate (Phase 0) and stop-the-line criteria
- Protects production customers during modularization

### Negative / risks
- Short-term process overhead (ADRs, fingerprint CI)
- Pressing/BAT local drift must stop growing during freeze

### Neutral
- Existing `InovCom\*` namespaces remain until Phase M7 aliases/renames

## Alternatives considered

1. **Big-bang rewrite on Laravel 12** — rejected (production risk).
2. **Microservices split now** — rejected (premature; modular monolith first).
3. **Continue three independent codebases forever** — rejected (blocks 90% reuse goal).

## Compliance

- [x] Aligns with `BPROO_PLATFORM_ARCHITECTURE_v1.md`
- [x] Migration roadmap phase: M0
- [x] Public contracts unchanged
