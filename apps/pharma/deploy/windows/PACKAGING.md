# Packaging Windows — Bproo Pharma Desktop

**Branch:** `feature/offline-runtime`  
**Does not change** SaaS Docker deploys.

## What you get

| Artefact | Rôle |
|----------|------|
| `dist/payload/` | App + `vendor/` (copies) + **PHP portable** + launchers |
| `dist/bproo-pharma-desktop-VERSION.zip` | Package pour **Updates desktop** (CC) |
| `output/bproo-pharma-desktop-VERSION-setup.exe` | Installateur Inno Setup |

## Prérequis machine de build (toi)

1. PHP + Composer (pour builder)
2. [Inno Setup 6](https://jrsoftware.org/isdl.php) pour le `.exe`
3. Internet (téléchargement PHP windows.php.net + composer.phar)

## Build A → Z

```powershell
cd D:\Projects\bproo-platform\apps\pharma\deploy\windows

# 1) Payload + PHP portable (+ zip optionnel pour le canal updates)
.\build-release.ps1 -Version 0.1.0 -ControlCenterUrl "https://TON-CC" -ZipPayload

# 2) Installateur Windows
.\compile-installer.ps1 -Version 0.1.0
```

Sorties :

- `dist\payload\`
- `dist\bproo-pharma-desktop-0.1.0.zip` + `.sha256`
- `output\bproo-pharma-desktop-0.1.0-setup.exe` + `.sha256`

## Publier l’update dans Control Center

1. CC → **Updates desktop**
2. Produit `pharma`, version `0.1.0`
3. Soit upload du zip, soit URL CDN + coller le SHA-256 du fichier `.sha256`
4. **Publier**

Les installs feront `desktop:updates-check` / `updates-apply`.

## Chez le client (installateur)

1. Lancer `bproo-pharma-desktop-*-setup.exe`
2. L’assistant exécute **First-Run-Setup** (SQLite + migrate)
3. Cocher **Activer la licence** → saisir le code CC
4. Lancer **Bproo Pharma Desktop** (raccourci) → `http://127.0.0.1:8003`

**Plus besoin de PHP système** sur le PC client si le payload contient `runtime\php`.

## Chez le client (sans Inno — zip)

1. Dézipper le payload
2. `First-Run-Setup.ps1`
3. `Activate-Licence.ps1`
4. `Start-BprooPharma.cmd`

## Options build utiles

```powershell
.\build-release.ps1 -Version 0.2.0 -PhpVersion 8.3.21 -SkipComposer   # si vendor déjà ok
.\build-release.ps1 -Version 0.2.0 -SkipPhpDownload                   # PHP déjà en cache/.cache
.\compile-installer.ps1 -IsccPath "C:\Prog\Inno Setup 6\ISCC.exe"
```

## Limites honnêtes

- Le premier build est **lourd** (vendor + PHP ~50–100 Mo+).
- `updates:apply` stage/verify encore ; le remplacement “in-place” complet du dossier installé reste à brancher sur le zip CC.
- Les path-repos Composer sont **mirrored** (`COMPOSER_MIRROR_PATH_REPOS=1`) pour que le zip soit autonome.

## Fichiers

| Script | Rôle |
|--------|------|
| `build-release.ps1` | Assemble payload + PHP + zip |
| `compile-installer.ps1` | ISCC → setup.exe |
| `innosetup/bproo-pharma.iss` | Définition Inno |
| `First-Run-Setup.ps1` / `Activate-Licence.ps1` / `Start-BprooPharma.*` | Générés dans le payload |
