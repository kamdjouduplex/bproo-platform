# Phase 8 — Cross-Vertical Contracts

**Status: COMPLETE (ready for testing)**  
**Date: 2026-07-29**

## Objectives (roadmap)

Stable Interfaces: `ClientsApi`, `ItemsApi`, `StockApi`, `InvoicingApi`, `PurchasingApi`. BAT adapters. Reports depend on Interfaces.

## Delivered

### Core + BAT vertical contracts

| Contract | ERP / shared | BAT |
|---|---|---|
| `StockApi` | `inventory` adapter | local `stock` adapter |
| `InvoicingApi` | `invoicing` service | `bat-invoicing` adapter |
| `PurchasingApi` | `purchases` service | `bat-purchasing` adapter |
| `ClientsApi` / `ItemsApi` | pre-existing | pre-existing |

### Report DIP (full)

`ReportingService` no longer queries domain tables directly. It resolves:

- `SalesApi`, `QuotationsApi`, `StockApi`
- `InvoicingApi`, `PurchasingApi`
- `ExpensesApi`, `LossesApi`, `DebtsApi`, `PayrollApi`

Only leftover schema peek: optional `clients` presence guard for client-performance assembly.

### Extra report contracts (ERP modules)

Added for report isolation (no BAT POS/expenses equivalents required):

- `SalesApi` → `packages/inovcom/sales`
- `QuotationsApi` → `packages/inovcom/quotations`
- `ExpensesApi` → `packages/inovcom/expenses`
- `LossesApi` → `packages/inovcom/losses`
- `DebtsApi` → `packages/inovcom/debts`
- `PayrollApi` → `packages/inovcom/hr`

## How to test

1. **Boot**
   - `cd apps/erp && php artisan about`
   - `cd apps/pressing && php artisan about`
   - `cd apps/bat && php artisan about`

2. **ERP / Pressing (with modules enabled)**
   - Open Reporting / dashboard KPIs
   - Check sales totals, stock alerts, invoice summary, purchases total, expenses/losses
   - Explorer reports: factures, ventes direct, stock low/out, CA HT/TVA

3. **Bindings smoke (ERP)**
   ```bash
   php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach ([InovCom\Kernel\Contracts\SalesApi::class, InovCom\Kernel\Contracts\InvoicingApi::class, InovCom\Kernel\Contracts\StockApi::class, InovCom\Kernel\Contracts\PurchasingApi::class] as \$c) { echo (\$app->bound(\$c) ? 'OK ' : 'NO ').\$c.PHP_EOL; }"
   ```

4. **BAT**
   - Facturation / achats / stock screens still load
   - `InvoicingApi` / `PurchasingApi` / `StockApi` bound (SalesApi will be unbound — expected)

## Optional later

- Widen APIs with mutation methods if cross-vertical writers need them
- `SuppliersApi` if provider metrics are needed in reports
- BAT adapter for `QuotationsApi` over `devis` if BAT reporting is added
