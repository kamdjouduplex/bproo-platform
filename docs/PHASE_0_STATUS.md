# Phase 0 Status — Freeze & Inventory

| Field | Value |
|---|---|
| **Phase** | M0 — Freeze & Inventory |
| **Started** | 2026-07-28 |
| **Updated** | 2026-07-28 (cleanup + rename) |
| **Status** | **Engineering complete** — ops can fill live company inventory when DB access available |
| **Roadmap** | `docs/MIGRATION_ROADMAP.md` v1.1 |

---

## Naming

| Old | Canonical |
|---|---|
| `bproo-erp-pos` | **`bproo-erp`** (folder renamed) |

POS remains a **capability** of ERP (`sales` + `caisse`), not a separate folder.

---

## Repository cleanup (done)

| Repo | Working tree | Latest commit | Baseline tag (local) |
|---|---|---|---|
| `bproo-erp` | **clean** | `0bb31ce` Add updated Bproo brand logo asset | `erp-prod-baseline` → `0bb31ce` |
| `bproo-pressing` | **clean** | `4868db9` Add pressing vertical package… | `pressing-prod-baseline` → `4868db9` |
| `bproo-bat/bproo-bat` | **clean** | `3cd3be9` Add construction domain packages… | `bat-prod-baseline` → `3cd3be9` |

ERP & Pressing are **1 commit ahead** of `origin/trams-negoce` (not pushed).  
BAT `new-skin` has no upstream tracking in status output.

**Tags were not pushed.** Push when ready:

```bash
# bproo-erp
git push origin trams-negoce
git push origin erp-prod-baseline

# bproo-pressing
git push origin trams-negoce
git push origin pressing-prod-baseline

# bproo-bat/bproo-bat
git push -u origin new-skin   # if needed
git push origin bat-prod-baseline
```

---

## Completed artifacts

| Item | Location |
|---|---|
| ADR template + ADR 0001 | `docs/adr/` |
| Freeze policy | `docs/FREEZE_POLICY.md` |
| Smoke runbooks | `docs/runbooks/` |
| Company inventory template | `docs/inventory/COMPANY_MODULE_INVENTORY.md` |
| Module catalogue (from config) | `docs/inventory/MODULE_CATALOG.md` |
| Fingerprint tool + CSV | `tools/fingerprint/` · `docs/fingerprint/` |
| Docs index | `docs/README.md` |

### Module catalogue counts

- ERP: **26** modules  
- Pressing: **36** modules (includes pressing_*)  
- BAT: **16** modules  

---

## Still optional / human

| Item | Notes |
|---|---|
| Live company rows in inventory | Needs Admin/SQL against staging or prod |
| Staging smoke sign-off | Run `docs/runbooks/smoke-*.md` |
| Push commits + tags | Your call |
| Announce freeze to team | Point them at `docs/FREEZE_POLICY.md` |

---

## Exit criteria

- [x] Three baseline tags on **clean** HEADs  
- [x] Module catalogue seeded  
- [ ] Live company inventory (blocked without DB)  
- [x] Four smoke runbooks  
- [x] Fingerprint tooling  
- [x] Freeze policy  
- [x] Repos cleaned (committed WIP)  
- [x] ERP named `bproo-erp`  

**Ready for M1 (monorepo scaffold)** whenever you say go: move to `apps/erp`, `apps/pressing`, `apps/bat`.
