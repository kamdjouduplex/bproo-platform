# Smoke runbook — Admin / Control plane

**Scope:** Platform Admin inside each product today (`/admin`).  
**Control Center:** use [`smoke-control-center.md`](smoke-control-center.md) for `apps/control-center` (M4b).

**Approx time:** 10–15 minutes per product

## Preconditions

- [ ] Platform admin user exists in landlord DB  
- [ ] At least one company in each product being tested  

## Checks (every product: ERP, Pressing, BAT)

| Step | Action | Expected |
|---|---|---|
| 1 | `/admin/login` | Form |
| 2 | Login | Dashboard |
| 3 | List companies/tenants | Rows load |
| 4 | Open one company | Detail / settings |
| 5 | Modules / packages UI | Toggle UI loads |
| 6 | Do **not** toggle prod modules casually | Staging only for enable/disable |
| 7 | Plans / subscription (ERP & Pressing) | Loads; BAT may be plan fields only |
| 8 | Module events / audit (if present) | Loads / export OK |
| 9 | Logout | Session cleared |

## Staging-only destructive checks

| Step | Action | Expected |
|---|---|---|
| A | Create throwaway company | Provisioning → ready |
| B | Enable a small module | Migrations + permissions |
| C | Disable module | Routes gated |

**Never** create/delete real customer DBs from this runbook in production.

## Sign-off

| Field | Value |
|---|---|
| Date | |
| Tester | |
| Products tested | ERP / Pressing / BAT |
| Environment | |
| Result | PASS / FAIL |
| Notes | |
