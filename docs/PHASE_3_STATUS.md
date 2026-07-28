# Phase 3 Status — Reconcile Drifted ERP↔Pressing Packages

| Field | Value |
|---|---|
| **Phase** | M3 — Share remaining inovcom packages (reconciled winners) |
| **Date** | 2026-07-29 |
| **Status** | **Complete** (local) |

---

## Winner map

| Package | Canonical source | Notes |
|---|---|---|
| clients | ERP | |
| caisse | ERP | POS cash drawer |
| quotations | ERP | |
| invoice_payments | ERP | |
| reporting | ERP | |
| invoicing | ERP | Keeps installment schedules / `InvoiceSchedule` |
| configuration | ERP | |
| users | Pressing | `assigned_agence_id` + UX polish |
| attendance | Pressing | agence-aware |
| expenses | Pressing | agence-aware |
| stock | ERP base + Pressing overlays | Overlay: `StockService.php`, `StorageLocationService.php` (unique-constraint fix) |

Canonical location: `packages/inovcom/{name}`

## Shared tree after M3

All former ERP↔Pressing `inovcom/*` packages now live under `packages/inovcom/` (27 packages).  
`apps/erp/packages/inovcom` and `apps/pressing/packages/inovcom` have **no leftover package dirs**.

BAT keeps its own vertical packages under `apps/bat/packages/inovcom/` (unchanged — M6).

## Composer changes

`apps/erp/composer.json` and `apps/pressing/composer.json`:

- Path repos for M3 packages → `../../packages/inovcom/{pkg}`
- Autoload PSR-4 → `../../packages/inovcom/{pkg}/src/`
- `composer update inovcom/*` refreshed junctions (11 installs each)

## Verification

- [x] Composer junctions for M3 packages → `packages/inovcom/*`
- [x] `php artisan about` boots on ERP and Pressing
- [x] Zero in-app duplicates of shared packages
- [x] Fingerprint tool lists all 27 shared packages
- [ ] Staging smoke: POS sale, invoice schedule, Pressing order + agence user (ops)

## Next

**M4** — Extract platform packages (Admin / tenancy / billing foundations).  
**M4b** — Control Center MVP (`apps/control-center`) after M4.
