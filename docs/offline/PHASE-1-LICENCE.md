# Offline Phase 1 — Licence (Control Center)

**Status:** implemented on `feature/offline-runtime`  
**ADR:** [0002](../adr/0002-offline-desktop-runtime.md)

## What this adds (additive only)

| Piece | Role |
|-------|------|
| `desktop_installs` table | One row per stand-alone machine / install |
| `DesktopLicenceService` | Issue code, activate, heartbeat, HMAC token |
| `POST /api/licence/activate` | First online activation |
| `POST /api/licence/heartbeat` | Refresh offline grace window |
| Admin UI | Entreprise → **Installations desktop** |

**Not changed:** `SubscriptionService`, SaaS `grace_ends_at`, `hasActiveSubscription` semantics for cloud apps, `EnsureTenantActive`.

SaaS subscription remains the commercial source of truth: activate / heartbeat refuse if the tenant has no active SaaS subscription.

## Ops

1. Migrate Control Center landlord DB:
   ```bash
   php artisan migrate --path=database/migrations/2026_09_15_120000_create_desktop_installs_table.php
   ```
   (or full `php artisan migrate` on CC)
2. Optional `.env` on Control Center:
   - `DESKTOP_LICENCE_SIGNING_KEY` (defaults to `APP_KEY`)
   - `DESKTOP_LICENCE_HEARTBEAT_DAYS=7`
   - `DESKTOP_LICENCE_OFFLINE_GRACE_DAYS=21`
3. From tenant fiche → **Installations desktop** → generate activation code.
4. Desktop client (future runtime) calls CC:
   - `POST /api/licence/activate` `{ activation_code, fingerprint, product_key?, app_version?, os? }`
   - `POST /api/licence/heartbeat` `{ install_uuid, token, fingerprint, app_version?, os? }`

## Token

HMAC-SHA256 over base64url(JSON claims). Claims include `install_uuid`, `token_version`, modules, `offline_access_expires_at`. Revoke bumps `token_version` so old tokens fail verify.

## Offline policy (desktop runtime, Phase 4+)

While offline, allow **read-write** until `offline_access_expires_at`, then **read-only**. This is enforced locally from the last signed token; CC does not need to be reachable for the grace window.
