# Offline Phase 4b — Windows packaging

See **[apps/pharma/deploy/windows/PACKAGING.md](../../apps/pharma/deploy/windows/PACKAGING.md)**.

Quick:

```powershell
cd apps\pharma\deploy\windows
.\build-release.ps1 -Version 0.1.0 -ZipPayload
.\compile-installer.ps1 -Version 0.1.0
```

Client: run `*-setup.exe` → First-Run-Setup → Activate-Licence → Start.
