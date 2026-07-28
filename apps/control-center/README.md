# Bproo Control Center

Platform ops host (`apps/control-center`). MVP parity with product `/admin`:

- Companies (tenants) · provisioning · health
- Modules enable / packages
- Plans · subscriptions · payments
- Module events · platform user login

Uses shared `packages/platform/*` and the **same landlord DB** as ERP/Pressing Admin (expand/contract — do not migrate company business data here).

## Local

```bash
cp .env.example .env   # set DB_* to landlord DB
composer install
php artisan key:generate
php artisan serve --port=8010
```

Open `http://127.0.0.1:8010/admin/login`.

Product `/admin` stays available until ops cutover.
