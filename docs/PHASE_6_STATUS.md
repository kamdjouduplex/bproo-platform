# Phase 6 — BAT Foundation Align (Safe)

**Status: COMPLETE**
**Date: 2026-07-29**

## What was done

### Shared kernel enriched with BAT workflow/audit foundation

The shared `packages/inovcom/kernel` now includes all traits and models that BAT introduced locally:

| File | Purpose |
|---|---|
| `Traits/WorkflowStateMachine.php` | State-machine enforcement on any model with a `status` column |
| `Traits/Auditable.php` | Automatic audit logging (create/update/delete) |
| `Traits/LazyModuleBoot.php` | Merged BAT's tenant-aware lazy boot logic (was a stub) |
| `Models/AuditLog.php` | Audit log model |
| `Exceptions/InvalidWorkflowTransitionException.php` | Workflow transition error |
| `Support/ServiceCatalog.php` | Service catalogue helper |

### BAT kernel pointed to shared tree

- `apps/bat/composer.json` path repo: `./packages/inovcom/kernel` → `../../packages/inovcom/kernel`
- PSR-4 autoload: `packages/inovcom/kernel/src/` → `../../packages/inovcom/kernel/src/`
- BAT local `packages/inovcom/kernel/` directory removed
- Vendor junction recreated to point to shared kernel

### ItemsApi contract unified

`InovCom\Kernel\Contracts\ItemsApi::getItemPrice()` second parameter changed from type-specific (`?int` for ERP, `string` for BAT) to `mixed $context = null`. Both ERP and BAT implementations updated to interpret context appropriately.

### BAT-only packages stay local (ADR-009)

Per ADR-009, the following BAT foundation packages remain under `apps/bat/packages/inovcom/`:

- `items` (diverged model/views from shared)
- `branding` (diverged views)
- `users` (diverged views/models)
- `clients` (BAT-specific, no merge)
- `stock` (BAT-specific, no merge)
- `dms` (BAT-only, no shared equivalent)
- `rh` (BAT-only, no shared equivalent)

These will be addressed in M7 (SOLID Splits) with proper interface extraction and renaming.

## Verification

All three applications boot successfully:
- `apps/erp` — `php artisan about` ✓
- `apps/pressing` — `php artisan about` ✓
- `apps/bat` — `php artisan about` ✓

## Deferred to M7

- Rename packages to architecture catalogue names (users→users+permissions, branding→settings, etc.)
- Merge diverged items/branding/users implementations behind shared interfaces
- `bproo/platform-*` packages for BAT (requires resolving App\ namespace conflicts)
