# Module catalogue inventory (seeded from config)

**Date:** 2026-07-28  
**Source:** each product `config/modules.php` (not live company enablement)  
**Note:** Fill company rows in `COMPANY_MODULE_INVENTORY.md` from Admin/SQL when DB access is available.

## ERP (`bproo-erp`)

Modules declared: 26

```
users, items, clients, providers, sales, stock, purchases, foreign_purchases, inventory, expenses, caisse, losses, debts, quotations, reservations, prospects, invoicing, invoice_payments, returns, reporting, payroll, attendance, tickets, configuration, batches, prescriptions
```

## Pressing (`bproo-pressing`)

Modules declared: 36

```
users, items, clients, providers, sales, stock, purchases, foreign_purchases, inventory, expenses, caisse, losses, debts, quotations, reservations, prospects, invoicing, invoice_payments, returns, reporting, payroll, attendance, tickets, configuration, batches, prescriptions, agences, pressing_clients, pressing_orders, pressing_workflow, pressing_fin_production, pressing_settings, pressing_deliveries, pressing_reports, pressing_consumables, pressing_loyalty
```

## BAT (`bproo-bat/bproo-bat`)

Modules declared: 16

```
clients, offres, devis, items, projets, prestations, planning, suivi, maintenance, facturation, achats, dms, stock, logistique, users, configuration
```

## Critical modules (migration smoke)

| Product | Critical keys |
|---|---|
| ERP / POS | sales, caisse, stock, items, users |
| Pressing | pressing_orders, pressing_workflow, agences, users |
| BAT | clients, offres, devis, projets, facturation, users |
