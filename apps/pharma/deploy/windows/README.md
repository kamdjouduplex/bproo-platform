# Bproo Pharma — Windows desktop install (Phase 4)

Stand-alone single-shop packaging of the **same** Pharma Laravel app.
Control plane remains Control Center (licence / updates / OUT sync).

## Prerequisites

- PHP 8.1+ on PATH (`php -v`)
- Composer on PATH
- Control Center reachable (`CONTROL_CENTER_URL`)
- Activation code from CC → Entreprise → Installations desktop

## Quick start (dev machine)

```powershell
cd apps\pharma\deploy\windows
.\install.ps1
.\start.ps1
```

Then in another shell (from `apps\pharma`):

```powershell
$env:DESKTOP_RUNTIME=1
php artisan desktop:activate "XXXX-XXXX-XXXX-XXXX"
php artisan desktop:heartbeat
php artisan desktop:enqueue sale.created
php artisan desktop:sync-out
php artisan desktop:updates-check --version=0.0.1
php artisan desktop:updates-apply --version=0.0.1
```

## Files

| Script | Role |
|--------|------|
| `install.ps1` | Copy env, sqlite, composer, key, migrate |
| `start.ps1` | `php artisan serve` |
| `schedule-heartbeat.ps1` | Example Task Scheduler registration |
| `innosetup/bproo-pharma.iss` | Future GUI installer skeleton |

SaaS Docker deploy under `../docker/` is unchanged.
