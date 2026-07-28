# Smoke runbook — Control Center

**Scope:** `apps/control-center` (M4b MVP)  
**URL (local):** `http://127.0.0.1:8010/admin`  
**Landlord DB:** Same schema as product Admin (ERP/Pressing) — platform data only.

**Approx time:** 10–15 minutes

## Preconditions

- [ ] CC `.env` points at landlord DB (same as ERP Admin)
- [ ] Platform admin user exists
- [ ] At least one company (tenant) row for list UI
- [ ] Product `/admin` still available (parallel run — do not remove yet)

## MVP checks

| Step | Action | Expected |
|---|---|---|
| 1 | `GET /` | Redirect to `/admin` |
| 2 | `/admin/login` | Control Center login |
| 3 | Login | Dashboard (companies / modules KPIs) |
| 4 | Companies (`/admin/tenants`) | List loads |
| 5 | Open company settings | Loads |
| 6 | Health | Provisioning statuses visible |
| 7 | Modules / Packages | Marketplace UI loads |
| 8 | Enable modules UI | Toggle UI loads (staging only for toggles) |
| 9 | Plans + company subscription | Loads |
| 10 | Module events (+ export) | Loads / downloads |
| 11 | Logout | Session cleared |

## Staging-only destructive

| Step | Action | Expected |
|---|---|---|
| A | Create throwaway company from CC | Provisioning → ready |
| B | Open company in ERP/Pressing host | Login works |
| C | Suspend / billing action from CC | Product host respects gate |

**Never** provision/delete real customer DBs from this runbook in production.

## Sign-off

| Field | Value |
|---|---|
| Date | |
| Tester | |
| Environment | |
| Result | PASS / FAIL |
| Notes | |
