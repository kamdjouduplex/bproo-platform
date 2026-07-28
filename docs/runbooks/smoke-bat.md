# Smoke runbook — BAT (Construction)

**Product:** BAT / Construction ERP  
**App path:** `apps/bat` (formerly `bproo-bat/bproo-bat`)  
**Approx time:** 20–30 minutes

## Preconditions

- [ ] App deployed; company provisioned  
- [ ] Modules: clients, offres, devis, projets, facturation (minimum)  

## 1. Admin

| Step | Action | Expected |
|---|---|---|
| 1.1 | `/admin/login` | OK |
| 1.2 | Tenants + modules | Core modules enabled |
| 1.3 | Health / analytics (if present) | OK |

## 2. Company login

| Step | Action | Expected |
|---|---|---|
| 2.1 | `/app/login?tenant={code}` | Dashboard |
| 2.2 | Clients first in nav | OK |

## 3. Commercial → execution (critical)

| Step | Action | Expected |
|---|---|---|
| 3.1 | Create/select client | OK |
| 3.2 | Create offre (projet category) | Kanban/status OK |
| 3.3 | Create devis from offre | Lines + totals |
| 3.4 | Send / accept quote | Status accepted |
| 3.5 | Project created (or create from quote) | Project visible |
| 3.6 | Optional: site report / task board | Saves |
| 3.7 | Create invoice from quote/project | PDF OK |
| 3.8 | Record payment | Receipt OK |

## 4. Optional modules

| Step | Action | Expected |
|---|---|---|
| 4.1 | Maintenance contract path | OK if enabled |
| 4.2 | Achat linked to project | Cost updates if designed |
| 4.3 | Logistique delivery complete | Stock listener if stock on |

## 5. AuthZ

| Step | Action | Expected |
|---|---|---|
| 5.1 | Non-privileged role | Cannot accept devis / record payment |

## Sign-off

| Field | Value |
|---|---|
| Date | |
| Tester | |
| Environment | |
| Company code | |
| Build / commit | |
| Result | PASS / FAIL |
| Notes | |
