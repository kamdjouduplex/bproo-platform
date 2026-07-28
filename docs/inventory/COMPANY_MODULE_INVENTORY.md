# Company / module inventory template (Phase 0)

Fill one row per **company (tenant)** from each product Admin.
Do not put secrets (DB passwords) in this file — store codes and metadata only.

## How to export

### Option A — Admin UI (manual)
1. Open `/admin` on each product.
2. For each company, record fields below.
3. Open company Modules and list enabled module keys.

### Option B — SQL on Control / landlord DB (ops)
```sql
-- Adjust table/column names if your schema differs slightly
SELECT code, name, is_active, provisioning_status, type, created_at
FROM tenants
ORDER BY code;

SELECT t.code AS company_code, m.key AS module_key, tm.enabled
FROM tenant_modules tm
JOIN tenants t ON t.id = tm.tenant_id
JOIN modules m ON m.id = tm.module_id
ORDER BY t.code, m.key;
```

### Option C — Artisan (if available in your build)
Document any existing `tenants:list` / export command here when added.

---

## Inventory sheet

| Product | Company code | Display name | Active | Provisioning | Type / vertical | Enabled modules (comma-separated) | Critical? (Y/N) | Pilot candidate? | Notes |
|---|---|---|---|---|---|---|---|---|---|
| ERP | | | | | | | | | |
| ERP | | | | | | | | | |
| Pressing | | | | | | | | | |
| BAT | | | | | | | | | |

## Critical modules by product (reference)

| Product | Must-not-break modules |
|---|---|
| ERP / POS | `sales`, `caisse`, `stock`, `items`, `users` |
| Pressing | `pressing_orders`, `pressing_workflow`, `agences`, `users` |
| BAT | `clients`, `offres`, `devis`, `projets`, `facturation`, `users` |

## Sign-off

| Field | Value |
|---|---|
| Exported by | |
| Date | |
| Environments covered | staging / production |
| File attachment | (CSV/SQL dump location — no secrets) |
