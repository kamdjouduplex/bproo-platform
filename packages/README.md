# Shared Composer packages

## Phase M2–M3 (done)

Retail/domain packages — **single source of truth** for ERP and Pressing:

```
packages/inovcom/
  attendance, batches, branding, caisse, clients, configuration, debts,
  expenses, inventory, invoice_payments, invoicing, items, kernel, losses,
  payroll, prescriptions, prospects, providers, purchases, quotations,
  reporting, reservations, returns, sales, stock, tickets, users
```

Apps consume them via Composer path repositories:

```json
{ "type": "path", "url": "../../packages/inovcom/sales" }
```

## Phase M4 (done for ERP/Pressing)

Control-plane packages:

```
packages/platform/
  tenancy, modules, billing, admin, auth, printing
```

Composer names: `bproo/platform-*`. Classes keep `App\*` namespaces for compatibility.

## Phase M4b (done)

Ops host: `apps/control-center` — consumes platform packages; same landlord DB as product Admin.

## Phase M5 (done)

Vertical plugins:

```
packages/verticals/
  pressing, bat-offers, bat-quotes, bat-projects, bat-maintenance,
  bat-planning, bat-site-tracking, bat-logistics, bat-purchasing, bat-invoicing
```

## Later phases

- M6: BAT foundation align (shared platform / users)
- M7: Composer/vendor rename toward `bproo/*` + `platform/core` from kernel
