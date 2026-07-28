# Smoke runbook — Pressing

**Product:** Pressing  
**App path:** `apps/pressing` (formerly `bproo-pressing`)  
**Approx time:** 20–30 minutes

## Preconditions

- [ ] App deployed; company type `pressing` (or equivalent)  
- [ ] Pressing modules enabled: agences, orders, workflow, deliveries (minimum)  
- [ ] Reception user + optional production/driver roles available  

## 1. Admin

| Step | Action | Expected |
|---|---|---|
| 1.1 | `/admin/login` | OK |
| 1.2 | Company list + modules | Pressing modules on |
| 1.3 | Health | OK |

## 2. Company login

| Step | Action | Expected |
|---|---|---|
| 2.1 | `/app/login?tenant={code}` | Dashboard (pressing profile OK) |
| 2.2 | Agences visible (if multi-branch) | Scoped data OK |

## 3. Order lifecycle (critical)

| Step | Action | Expected |
|---|---|---|
| 3.1 | Create pressing client (or pick existing) | Saved |
| 3.2 | Create order with articles | Pricing mode OK |
| 3.3 | Record payment / credit request path | Settlement rules OK |
| 3.4 | Tri / constitution if required | Completes; moves to production |
| 3.5 | Advance Kanban stages | Stage history recorded |
| 3.6 | Fin production | Order ready |
| 3.7 | Delivery (pickup or domicile) | Blocked if unpaid without credit |
| 3.8 | Print deposit/ticket/label | OK |
| 3.9 | Public QR tracking (no auth) | Shows status |

## 4. Optional

| Step | Action | Expected |
|---|---|---|
| 4.1 | Loyalty points after paid order | Accrual OK |
| 4.2 | Consumable issue (if module on) | Stock decreases |
| 4.3 | Notification log (WhatsApp/SMS staging) | Attempt logged |

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
