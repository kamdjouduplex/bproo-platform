# Offline Phase 2 — Update channel (Control Center)

**Status:** implemented on `feature/offline-runtime`  
**ADR:** [0002](../adr/0002-offline-desktop-runtime.md)

## What this adds (additive only)

| Piece | Role |
|-------|------|
| `desktop_releases` table | Versioned packages per product + channel |
| `DesktopUpdateService` | Draft / publish / yank + signed manifest |
| `POST /api/updates/check` | Desktop asks “is there a newer build?” |
| `GET|POST /api/updates/download/{uuid}` | Authenticated package fetch (or CDN redirect) |
| Admin UI | Sidebar **Updates desktop** |

**Not changed:** `SubscriptionService`, Phase 1 licence gates for SaaS cloud apps, tenant middleware.

Auth for update APIs = valid **Phase 1 licence token** (`install_uuid` + `token` + `fingerprint`). Inactive tenant / expired SaaS subscription → refuse.

## Ops

1. Migrate Control Center:
   ```bash
   php artisan migrate
   ```
2. Optional `.env`:
   - `DESKTOP_UPDATE_SIGNING_KEY` (defaults to licence key / `APP_KEY`)
   - `DESKTOP_UPDATE_DISK=desktop_updates`
   - `DESKTOP_UPDATE_MANIFEST_TTL_HOURS=24`
3. Admin → **Updates desktop** → create draft (CDN URL + SHA-256, or upload file) → **Publier**.

## Desktop client contract

```http
POST /api/updates/check
{
  "install_uuid": "...",
  "token": "<licence token>",
  "fingerprint": "...",
  "current_version": "1.0.0",
  "channel": "stable",
  "product_key": "pharma"
}
```

Response when newer:

```json
{
  "ok": true,
  "update_available": true,
  "manifest": { "typ": "bproo.desktop.update.manifest", "version": "1.1.0", "package_sha256": "...", "apply": { ... } },
  "signed_manifest": "<payload>.<hmac>"
}
```

### Apply / rollback (desktop runtime — Phase 4)

The signed `apply` block tells the agent:

1. Download package, verify SHA-256  
2. Keep previous install (`keep_previous: true`)  
3. Replace / extract new build  
4. Run `healthcheck` (default `php artisan about`)  
5. On failure → restore previous (`rollback_on_healthcheck_fail: true`)

Control Center does **not** execute apply/rollback; it only publishes the signed contract.

## Smoke test (after publish)

Use the same PowerShell `Invoke-RestMethod` pattern as Phase 1 heartbeat, against `/api/updates/check` with `current_version` lower than the published one.
