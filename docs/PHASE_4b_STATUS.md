# Phase 4b Status — Control Center MVP

| Field | Value |
|---|---|
| **Phase** | M4b — Control Center MVP |
| **Date** | 2026-07-29 |
| **Status** | **Complete** (local scaffold + ops parity routes) |

---

## Deliverable

New host: **`apps/control-center`**

- Composer: `bproo/control-center`
- Consumes `bproo/platform-*` (+ inovcom packages for provision/module lifecycle)
- **Same landlord DB** as ERP/Pressing Admin (expand/contract — no company business data in CC DB)
- Product `/admin` **kept** for parallel ops

## MVP surface (parity with today’s Admin)

| Area | Route | Status |
|---|---|---|
| Platform login | `/admin/login` | ✓ |
| Dashboard | `/admin` | ✓ |
| Companies | `/admin/tenants*` | ✓ |
| Provisioning health | `/admin/tenants/health` | ✓ |
| Module enable | `/admin/tenants/modules` | ✓ |
| Packages / catalogue | `/admin/packages` | ✓ |
| Plans / subscriptions | `/admin/plans*`, `…/subscription` | ✓ |
| Module events | `/admin/module-events` | ✓ |
| Root redirect | `/` → `/admin` | ✓ |

**Deferred (post-MVP):** Platform CRM pipeline, third-party marketplace, custom domains polish, support tickets, advanced analytics, Takoly API keys.

## Local run

```bash
cd apps/control-center
cp .env.example .env   # DB_* = landlord
composer install --no-security-blocking
php artisan key:generate
php artisan serve --port=8010
```

Smoke: `docs/runbooks/smoke-control-center.md`

## Verification

- [x] `php artisan about` → Application Name **Bproo Control Center**
- [x] Admin routes resolve to platform Livewire/controllers
- [x] Platform package discovery (tenancy/admin/…)
- [ ] Staging smoke + provision from CC → open in ERP/Pressing (ops)

## Next

**M5** — Verticals as plugins (`packages/verticals/pressing`, BAT verticals).  
Optional: BAT Admin thin-link to CC; deprecate product `/admin` after ops sign-off.
