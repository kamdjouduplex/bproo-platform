# Phase 2 Status — Share Identical Packages

| Field | Value |
|---|---|
| **Phase** | M2 — Share 100% identical ERP↔Pressing packages |
| **Date** | 2026-07-29 |
| **Status** | **Complete** (local) |

---

## Shared packages (canonical)

Location: `packages/inovcom/{name}`

| Package | Notes |
|---|---|
| kernel | Foundation |
| items | Catalogue |
| sales | **POS** |
| purchases | + foreign purchases live in same package |
| providers | Suppliers |
| debts | |
| inventory | Stock counts |
| losses | |
| payroll | |
| batches | Pharmacy |
| prescriptions | Pharmacy |
| prospects | |
| reservations | |
| tickets | |
| returns | |
| branding | On disk (not always composer-required) |

## Apps still keep (local copies — M3) — **done in M3**

Formerly local: `users`, `clients`, `stock`, `caisse`, `expenses`, `reporting`, `attendance`, `configuration`, `quotations`, `invoicing`, `invoice_payments` → see `docs/PHASE_3_STATUS.md`.

## Verification

- [x] Composer junctions: `vendor/inovcom/sales` → `packages/inovcom/sales` (ERP + Pressing)
- [x] `php artisan about` boots on ERP and Pressing
- [x] Zero in-app duplicates of shared packages
- [ ] Staging smoke: POS sale + Pressing order (ops)

## Composer changes

`apps/erp/composer.json` and `apps/pressing/composer.json`:

- Path repos for shared packages → `../../packages/inovcom/{pkg}`
- Autoload PSR-4 for shared namespaces → `../../packages/inovcom/{pkg}/src/`
- `composer update inovcom/*` refreshed lock + junctions

## Next

**M3** — Reconcile drifted packages (`invoicing`, `stock`, `users`, `caisse`, …) into the shared tree.
