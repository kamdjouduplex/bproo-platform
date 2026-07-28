# Package freeze policy (Phases M0–M3)

**Effective:** 2026-07-28 (Phase 0 start)  
**Authority:** ADR 0001 · Migration Roadmap v1.1

## Rule

Do **not** create or deepen duplicate copies of shared packages across:

- `apps/erp/packages/inovcom/*` and `apps/pressing/packages/inovcom/*` for packages **not yet** shared
- Do **not** re-create copies of packages that live under `packages/inovcom/` (see Phase M2 list)

## Allowed

| Allowed | Example |
|---|---|
| Bugfix in one app if blocking production | Hotfix sale print — then port to canonical tree in M2+ |
| New code in **vertical-only** packages | `packages/pressing/*`, BAT `offres`/`devis`/… |
| Host app wiring | `config/modules.php`, theme, deploy |
| Docs / ADRs / smoke runbooks | `docs/**` |

## Forbidden until M2 canonical path exists

| Forbidden | Why |
|---|---|
| Copying a new `inovcom/foo` into a second app | Creates more dedupe debt |
| Divergent features in both ERP and Pressing copies of the same package | Fingerprint CI will fail tracked packages |
| Renaming module keys / permission keys | Breaks production entitlements |

## After M2

All ERP↔Pressing shared packages live under the monorepo `packages/` tree. Apps consume via Composer path repos. Fingerprint CI ensures a second copy does not reappear.

## Enforcement

- Human: PR review checklist
- Tool: `tools/fingerprint/compare-inovcom-packages.ps1`
- CI: run fingerprint on PRs once monorepo CI exists (M1+)
