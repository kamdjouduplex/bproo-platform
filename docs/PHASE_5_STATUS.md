# Phase 5 Status — Verticals as Plugins

| Field | Value |
|---|---|
| **Phase** | M5 — Verticals as plugins |
| **Date** | 2026-07-29 |
| **Status** | **Complete** (path moves; Composer names unchanged until M7) |

---

## Layout

```
packages/verticals/
  pressing/              ← kamfo/pressing (from apps/pressing/packages/pressing)
  bat-offers/            ← inovcom/offres
  bat-quotes/            ← inovcom/devis
  bat-projects/          ← inovcom/projets
  bat-maintenance/       ← inovcom/maintenance
  bat-planning/          ← inovcom/planning
  bat-site-tracking/     ← inovcom/suivi
  bat-logistics/         ← inovcom/logistique
  bat-purchasing/        ← inovcom/achats
  bat-invoicing/         ← inovcom/facturation
```

Composer **package names** stay `kamfo/pressing` / `inovcom/*` (rename in M7).  
Physical folders follow Architecture catalogue.

## Host wiring

| App | Change |
|---|---|
| `apps/pressing` | Path repo + PSR-4 → `../../packages/verticals/pressing` |
| `apps/bat` | Nine vertical path repos → `../../packages/verticals/bat-*` |
| `apps/erp` | Unchanged (no verticals) |

BAT foundation packages remain local until M6:  
`apps/bat/packages/inovcom/{kernel,users,clients,items,stock,branding,dms,rh}`

## Intentionally deferred

- Full DIP / Interfaces for Pressing consumables (still concrete `InovCom\Items` / `Stock`) — M8 contracts
- Composer rename to `bproo/vertical-*` — M7
- BAT consume shared `packages/inovcom` + `packages/platform` — **M6**

## Verification

- [x] Pressing: `kamfo/pressing` junctions to `packages/verticals/pressing`; `php artisan about` OK
- [x] BAT: vertical junctions + discover; `php artisan about` OK
- [x] ERP smoke unchanged (no vertical deps)
- [ ] Staging: Pressing order cycle + BAT devis→projet (ops)

## Next

**M6** — BAT foundation align (shared platform/core, users/settings injection; no financial schema merge).
