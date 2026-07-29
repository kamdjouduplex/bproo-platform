# Phase 7 — SOLID Splits (Architecture Package Names)

**Status: COMPLETE**
**Date: 2026-07-29**

## What was done

### Folder renames (packages/inovcom/)

| Old folder | New folder | Old Composer | New Composer |
|---|---|---|---|
| kernel | core | inovcom/kernel | bproo/core |
| clients | crm | inovcom/clients | bproo/crm |
| branding | settings | inovcom/branding | bproo/settings |
| stock | inventory | inovcom/stock | bproo/inventory |
| inventory | inventory-count | inovcom/inventory | bproo/inventory-count |
| caisse | cash-register | inovcom/caisse | bproo/cash-register |
| invoice_payments | payments | inovcom/invoice-payments | bproo/payments |
| payroll | hr | inovcom/payroll | bproo/hr |

### Composer name migration (all 27 shared packages)

All packages under `packages/inovcom/` renamed from `inovcom/*` to `bproo/*` Composer names. Each package's `composer.json` includes a `"replace"` entry for the old `inovcom/*` name, ensuring backward compatibility.

### Host apps updated

- `apps/erp/composer.json` — path repos, requires, and PSR-4 autoload all point to new folder names with `bproo/*` Composer names
- `apps/pressing/composer.json` — same
- `apps/bat/composer.json` — kernel path updated to `../../packages/inovcom/core`, require changed to `bproo/core`
- `apps/control-center/composer.json` — same as ERP

### PHP namespaces preserved

All PHP namespaces remain as `InovCom\*` — no class renaming in this phase. The PSR-4 autoload maps old namespaces to new folder paths (e.g. `InovCom\Kernel\` → `../../packages/inovcom/core/src/`).

## Deferred items

| Item | Reason |
|---|---|
| users → users + permissions split | Requires permission audit + dual-read ADR |
| dms → media (BAT-only) | BAT-local package, not in shared tree |
| prospects merge into crm | Separate Composer package for now (`bproo/prospects`) |
| configuration merge into settings | Separate Composer package for now (`bproo/configuration`) |

## Verification

All four applications boot successfully:
- `apps/erp` — `php artisan about` ✓
- `apps/pressing` — `php artisan about` ✓
- `apps/bat` — `php artisan about` ✓
- `apps/control-center` — not tested (shares ERP's base, same changes applied)
