# Phase 4 Status — Extract Platform Packages

| Field | Value |
|---|---|
| **Phase** | M4 — Extract platform packages (Admin still per-product URL) |
| **Date** | 2026-07-29 |
| **Status** | **Complete** for ERP + Pressing (local); BAT wiring deferred |

---

## Packages created

Location: `packages/platform/{name}` · Composer: `bproo/platform-*`

| Package | Contents | Notes |
|---|---|---|
| `tenancy` | Tenant, TenantSetting, TenantManager/Provisioner/Branding, store context, tenancy middleware, provision/multi-store jobs, TenantSettingsApplier | `SetCurrentStore` from **Pressing** (pressing-type guard) |
| `modules` | Module, TenantModule, ModuleEvent, registry/marketplace services, EnsureModuleEnabled, install/uninstall jobs, ModuleEvents | |
| `billing` | Plan, Subscription, TenantPayment, TenantBalanceTransaction, SubscriptionService | ERP/Pressing only |
| `admin` | Livewire Admin suite + ModuleEventsExportController + admin views | Views via `View::addLocation` |
| `auth` | Landlord `User`, Admin + Tenant AuthControllers | Login blades stay in apps |
| `printing` | DocumentMargin, PrintDocument, paginator/tax/line helpers, CashLedger | |
| `core` | **Deferred** | Still `inovcom/kernel` until M7 rename |

Namespaces stay **`App\*`** for one release (Composer PSR-4 merge) — no mass `use` rewrites. Host composers also map leftover `app/{Models,Services,…}` prefixes so product services keep resolving.

## Winner overlays (drift)

| File | Winner |
|---|---|
| TenantProvisioner timezone seed | ERP (`inovcom.default_timezone`) |
| ApplyTenantSettings / SetTenantConnection | ERP (+ TenantSettingsApplier) |
| SetCurrentStore | Pressing (skip stores for `pressing` type) |
| Admin TenantSettings | ERP |

## Left in host apps (product-specific)

- `DashboardService`, `PendingActionsService`
- `Livewire/Tenant/*` (Dashboard, NotificationBell, SubscriptionStatus) — partial notifications deferred
- Auth/login blades, `layouts.app`
- Framework middleware / providers / console commands

## Apps wired

- [x] `apps/erp` — path repos + require + junctions
- [x] `apps/pressing` — same
- [ ] `apps/bat` — **not in this commit** (different billing shape; align in M6 / follow-up)

## Verification

- [x] `php artisan about` boots ERP + Pressing
- [x] Classes resolve from `vendor/bproo/platform-*` junctions
- [x] Admin Livewire / Tenant model autoload OK
- [ ] Staging smoke: create company → provision → modules → plans/payments → company login → module 403

## Next

**M4b** — Control Center MVP (`apps/control-center`) — see `docs/PHASE_4b_STATUS.md`.  
Optional follow-up: wire BAT to tenancy/modules/auth (no billing force-merge).
