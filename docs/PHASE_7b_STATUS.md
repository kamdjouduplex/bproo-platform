# Phase 7b — UI Kit

**Status: COMPLETE**  
**Date: 2026-07-29**

## What changed

### New package: `packages/ui/core` (`bproo/ui-core`)

Scaffolded a dedicated UI kit package that provides **Blade anonymous components**:

- `app-layout` (renders `layouts.app` and forwards title/subtitle/actions + slot)
- `item-label`
- `file-type-icon`
- `export-btn`
- `ui-icon-box`

The package registers its components via `Blade::anonymousComponentPath(...)` in:
- `packages/ui/core/src/UiCoreServiceProvider.php`

### Host apps wired to the UI kit

`bproo/ui-core` was added to `require` + `repositories` in:
- `apps/erp/composer.json`
- `apps/pressing/composer.json`
- `apps/control-center/composer.json`
- `apps/bat/composer.json`

### Removed duplicated component Blade files

To ensure all `<x-*>` tags resolve from the UI kit package (no duplicated sources):

- `apps/erp/resources/views/components/`: removed `app-layout`, `item-label`, `export-btn`, `file-type-icon`, `ui-icon-box`
- `apps/pressing/resources/views/components/`: removed `app-layout`, `item-label`
- `apps/control-center/resources/views/components/`: removed `app-layout`, `item-label`, `export-btn`, `file-type-icon`, `ui-icon-box`
- `apps/bat/resources/views/components/`: removed `app-layout`

## Verification

All apps successfully booted after extraction:
- `apps/erp` — `php artisan about`
- `apps/pressing` — `php artisan about`
- `apps/control-center` — `php artisan about`
- `apps/bat` — `php artisan about`

