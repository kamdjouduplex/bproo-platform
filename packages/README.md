# Shared Composer packages

## Phase M2 (done)

These packages are the **single source of truth** for ERP and Pressing:

```
packages/inovcom/
  batches, branding, debts, inventory, items, kernel, losses, payroll,
  prescriptions, prospects, providers, purchases, reservations, returns,
  sales, tickets
```

Apps consume them via Composer path repositories:

```json
{ "type": "path", "url": "../../packages/inovcom/sales" }
```

Still **per-app** (drift — Phase M3):

`users`, `clients`, `stock`, `caisse`, `expenses`, `reporting`, `attendance`, `configuration`, `quotations`, `invoicing`, `invoice_payments`

## Later phases

- M4+: move toward `packages/platform/*` and `packages/shared/*` naming
- Verticals: `apps/pressing/packages/pressing`, `apps/bat/packages/inovcom/*`
