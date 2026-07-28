# Smoke runbook — ERP / POS

**Product:** ERP (+ POS capability)  
**App path:** `apps/erp` (formerly `bproo-erp` / `bproo-erp-pos`)  
**Environment:** staging first, then pilot company  
**Approx time:** 15–25 minutes

## Preconditions

- [ ] App deployed; DB control + at least one company DB reachable  
- [ ] Admin credentials available  
- [ ] Company code known (e.g. `demo`)  
- [ ] Modules enabled: `users`, `items`, `sales`, `stock`, `caisse` (minimum)

## 1. Admin

| Step | Action | Expected |
|---|---|---|
| 1.1 | Open `/admin/login` | Login form |
| 1.2 | Sign in as platform admin | Dashboard |
| 1.3 | Open Tenants / Companies list | Company visible; status active/ready |
| 1.4 | Open company modules | Critical modules enabled |
| 1.5 | Open health (if available) | DB OK / no critical error |

## 2. Company login

| Step | Action | Expected |
|---|---|---|
| 2.1 | Open `/app/login?tenant={code}` | Login form |
| 2.2 | Sign in as company admin/cashier | Dashboard loads |
| 2.3 | Sidebar shows Sales / Stock / Caisse | No 403 on those modules |

## 3. POS critical path

| Step | Action | Expected |
|---|---|---|
| 3.1 | Open Caisse → open session | Session open |
| 3.2 | Open Sales (POS form) | Cart UI |
| 3.3 | Add item with stock | Line totals correct |
| 3.4 | Pay (cash and/or mobile money split) | Sale saved |
| 3.5 | Print ticket/invoice | PDF/print OK |
| 3.6 | Park sale then resume (if used) | Works |
| 3.7 | Create a sale return (if permitted) | Stock/money consistent |
| 3.8 | Close caisse session | Report/export OK |

## 4. Stock / purchase sanity

| Step | Action | Expected |
|---|---|---|
| 4.1 | Stock lookup for sold item | Quantity decreased |
| 4.2 | Optional: receive a small PO | Stock increased |

## 5. AuthZ sanity

| Step | Action | Expected |
|---|---|---|
| 5.1 | User without `sales.create` | Cannot checkout / 403 |

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
