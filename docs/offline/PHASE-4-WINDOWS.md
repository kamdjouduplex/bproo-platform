# Offline Phase 4 — Windows desktop runtime

**Status:** scaffolded on `feature/offline-runtime`  
**ADR:** [0002](../adr/0002-offline-desktop-runtime.md)  
**Pilot product:** Pharma

## What this adds

| Piece | Role |
|-------|------|
| `packages/platform/desktop` | Agent Artisan (activate, heartbeat, outbox, updates) |
| Local `desktop_outbox_events` | Outbox on the **desktop** DB |
| `apps/pharma/deploy/windows/*` | `install.ps1` / `start.ps1` / Task Scheduler / Inno skeleton |
| `.env.desktop.example` | Single-tenant SQLite + `DESKTOP_RUNTIME=1` |

**Not changed:** SaaS Docker deploys, Control Center billing, multi-tenant cloud apps.

Cloud APIs consumed (already live):

- `POST /api/licence/activate|heartbeat`
- `POST /api/updates/check` + download
- `POST /api/sync/out`

## Enable on a machine

```powershell
cd apps\pharma
composer update bproo/platform-desktop --with-all-dependencies
cd deploy\windows
.\install.ps1 -ControlCenterUrl "http://127.0.0.1:8010"
.\start.ps1
```

Activate (code from CC → Installations desktop):

```powershell
cd apps\pharma
$env:DESKTOP_RUNTIME=1
php artisan desktop:activate "XXXX-XXXX-XXXX-XXXX"
php artisan desktop:heartbeat
php artisan desktop:enqueue sale.created
php artisan desktop:sync-out
php artisan desktop:updates-check --current=0.0.1
php artisan desktop:updates-apply --current=0.0.1
```

## Commands

| Command | Purpose |
|---------|---------|
| `desktop:activate {code}` | First licence |
| `desktop:heartbeat` | Refresh offline grace |
| `desktop:enqueue {type}` | Push event into local outbox |
| `desktop:sync-out` | Drain outbox → CC |
| `desktop:updates-check --current=` | Ask CC for newer package |
| `desktop:updates-apply --current=` | Download, SHA-256, stage, healthcheck, rollback marker |

State file: `storage/app/desktop/state.json`  
Packages / staging / rollback: `storage/app/desktop/{packages,staging,rollback}/`

## Honest limits (Phase 4 v1)

- Same Livewire Pharma app served locally (`artisan serve`) — not a rewritten native UI
- `updates:apply` stages + verifies; full atomic binary replace lands with CI-built zip + Inno packaging
- PHP must be installed on the PC (bundled PHP runtime = follow-up)

## Next

**Phase 5** — IN sync / multi-device.  
Also: CI artefact that feeds Inno Setup + bundled PHP.
