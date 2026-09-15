# Offline Phase 3 — OUT sync / cloud backup

**Status:** implemented on `feature/offline-runtime`  
**ADR:** [0002](../adr/0002-offline-desktop-runtime.md)

## What this adds (additive only)

| Piece | Role |
|-------|------|
| `desktop_sync_batches` | One row per push from a desktop install |
| `desktop_sync_events` | Append-only event log (idempotent by `event_id`) |
| `DesktopSyncService` | Ingest OUT + status |
| `POST /api/sync/out` | Push a batch of events |
| `POST /api/sync/out/status` | Cursor / counts for that install |
| Admin | **Sync desktop** + colonne Sync OUT sur Installations |

**Not changed:** `SubscriptionService`, SaaS tenant DBs (events are **not** replayed into cloud company DBs yet), Phase 1/2 APIs.

Auth = Phase 1 licence token (`install_uuid` + `token` + `fingerprint`).

## Ops

```bash
php artisan migrate
```

Optional `.env`: `DESKTOP_SYNC_MAX_EVENTS_PER_BATCH=200`

## Smoke test

```powershell
$eventId = [guid]::NewGuid().ToString()
$body = @{
  install_uuid = '556f4cc2-b1bc-4a4e-b619-164a353979d4'
  token        = 'TON_TOKEN_LICENCE'
  fingerprint  = 'pc-test-01'
  batch_uuid   = [guid]::NewGuid().ToString()
  app_version  = '0.1.0'
  events       = @(
    @{
      event_id       = $eventId
      type           = 'sale.created'
      occurred_at    = (Get-Date).ToString('o')
      schema_version = 1
      payload        = @{ local_sale_id = 'demo-1'; total = 1500; currency = 'XAF' }
    }
  )
} | ConvertTo-Json -Depth 6

Invoke-RestMethod -Method Post -Uri 'http://127.0.0.1:8000/api/sync/out' -ContentType 'application/json' -Body $body
```

Attendu : `ok: True`, `accepted: 1`. Relancer avec le **même** `event_id` → `duplicates: 1`.  
Dans le CC : **Sync desktop** montre le batch ; fiche Installations → colonne **Sync OUT**.

## Desktop runtime note

The local outbox (queue on the machine) is built in Phase 4 with the installer. Phase 3 is the **cloud receiver** only.
