# Phase 1 Status — Monorepo Scaffold

| Field | Value |
|---|---|
| **Phase** | M1 — Monorepo scaffold (zero behavior change) |
| **Started** | 2026-07-29 |
| **Status** | **Complete** (local). Push remote when ready. |
| **Roadmap** | `docs/MIGRATION_ROADMAP.md` v1.1 |

---

## Layout achieved

```
bproo-platform/
├── apps/erp/
├── apps/pressing/
├── apps/bat/
├── packages/          # placeholder README only
├── docs/
├── tools/
├── backups/git-bundles/
├── .github/
└── README.md
```

## Pre-move SHAs (preserved)

| App | Former path | SHA | Branch | Baseline tag |
|---|---|---|---|---|
| ERP | `bproo-erp` | `0bb31ce` | `trams-negoce` | `erp-prod-baseline` |
| Pressing | `bproo-pressing` | `4868db9` | `trams-negoce` | `pressing-prod-baseline` |
| BAT | `bproo-bat/bproo-bat` | `3cd3be9` | `new-skin` | `bat-prod-baseline` |

Record file: `backups/pre-monorepo-git-remotes.csv`  
Git bundles (full history including unpushed commits): `backups/git-bundles/*.bundle`

### Restore a bundle if needed

```bash
git clone backups/git-bundles/bproo-erp.bundle restored-erp
```

## What did not change

- Each app’s internal Composer path packages (`apps/*/packages/inovcom`)
- Runtime code, module keys, routes
- No shared `packages/shared` extraction yet (that is **M2**)

## Verification checklist

- [x] `apps/erp/artisan` exists  
- [x] `apps/pressing/artisan` exists  
- [x] `apps/bat/artisan` exists  
- [x] Fingerprint script points at `apps/erp` + `apps/pressing`  
- [ ] Staging deploy from new paths (ops)  
- [ ] Smoke runbooks once from new cwd (ops)  

## Next phase

**M2** — Share 100% identical `inovcom` packages into `packages/` and point ERP + Pressing Composer paths at them.
