# Bproo Pharma — Windows desktop install (Phase 4)

Stand-alone single-shop packaging of the **same** Pharma Laravel app.
Control plane remains Control Center (licence / updates / OUT sync).

## Packaging (recommandé)

Voir **[PACKAGING.md](./PACKAGING.md)** :

```powershell
.\build-release.ps1 -Version 0.1.0 -ZipPayload
.\compile-installer.ps1 -Version 0.1.0
```

## Dev rapide (PHP système déjà installé)

```powershell
.\install.ps1 -ControlCenterUrl "http://127.0.0.1:8000"
.\start.ps1
```

Puis :

```powershell
cd ..\..\
$env:DESKTOP_RUNTIME=1
$env:CONTROL_CENTER_URL="http://127.0.0.1:8000"
php artisan desktop:activate "XXXX-XXXX-XXXX-XXXX"
```

## Files

| Script | Role |
|--------|------|
| `build-release.ps1` | Payload + PHP portable + zip |
| `compile-installer.ps1` | Inno Setup → `.exe` |
| `install.ps1` / `start.ps1` | Bootstrap / serve (dev ou payload) |
| `schedule-heartbeat.ps1` | Task Scheduler |
| `innosetup/bproo-pharma.iss` | Définition installateur |

SaaS Docker deploy under `../docker/` is unchanged.
