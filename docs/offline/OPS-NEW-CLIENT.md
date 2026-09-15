# Nouveau client desktop — process A → Z (Phase 4)

Guide ops pour un **nouveau client offline** (ex. pharmacie).  
Le cloud (Control Center) reste le cerveau ; le PC du client tourne en local.

> Aujourd’hui c’est encore un **setup technique** (PHP + scripts). L’installateur Inno “double-clic” arrive ensuite.

---

## Vue d’ensemble

```
Toi (Afroinov)                         Client (PC boutique)
─────────────────                      ────────────────────
1. Créer entreprise + abonnement SaaS
2. Émettre code d’activation    ──►
3. (option) Publier une release
                                   4. Installer Pharma desktop
                                   5. Activer avec le code
                                   6. Utiliser l’app hors-ligne
                                   7. Heartbeat / sync quand online
```

L’abonnement SaaS **reste obligatoire** : sans abonnement actif, activate / heartbeat / updates / sync sont refusés.

---

## Étape 1 — Control Center : créer le client

1. Ouvre **Control Center** (chez toi : souvent `http://127.0.0.1:8000` ou `:8010`).
2. **Entreprises** → créer / ouvrir la société (type `pharma`).
3. **Facturation** → s’assurer qu’il y a un **abonnement actif** (plan + période).
4. Modules utiles activés (comme pour un SaaS).

Sans ça, le desktop ne pourra pas s’activer.

---

## Étape 2 — Émettre la licence desktop

1. Fiche entreprise → **Installations desktop**.
2. Produit `pharma`, libellé ex. `Pharmacie Dupont — PC caisse`.
3. **Générer le code** → tu obtiens `XXXX-XXXX-XXXX-XXXX`.
4. Envoie ce code au client (SMS / WhatsApp / papier).

Un code = **une machine**. Si le PC change → révoquer + nouveau code.

---

## Étape 3 — (Une fois) Publier les mises à jour

Sur CC → **Updates desktop** :

1. Créer un brouillon (version semver, ex. `0.1.0`).
2. Fichier zip **ou** URL CDN + SHA-256.
3. **Publier**.

Les installs déjà activées feront `desktop:updates-check` / `updates-apply` plus tard.

---

## Étape 4 — Sur le PC du client : installer

### Prérequis machine

- Windows 10/11  
- **PHP 8.1+** dans le PATH  
- **Composer**  
- Accès Internet **au moins pour l’activation** (puis grâce offline ~21 jours)

### Installation

Sur une **copie dédiée** de l’app (ne pas mélanger avec ton Pharma SaaS de dev) :

```powershell
cd apps\pharma\deploy\windows
.\install.ps1 -ControlCenterUrl "https://TON-CONTROL-CENTER"
.\start.ps1
```

Ça prépare :

- `.env` desktop (`DESKTOP_RUNTIME=1`)
- SQLite local
- migrations (dont `desktop_outbox_events`)
- dossiers `storage/app/desktop/...`

Puis l’app écoute en local (ex. `http://127.0.0.1:8003`).

---

## Étape 5 — Activer la licence

```powershell
cd apps\pharma
$env:DESKTOP_RUNTIME = "1"
$env:CONTROL_CENTER_URL = "https://TON-CONTROL-CENTER"

php artisan desktop:activate "XXXX-XXXX-XXXX-XXXX"
php artisan desktop:heartbeat
```

État stocké dans : `storage/app/desktop/state.json`  
(`install_uuid`, `token`, `fingerprint`)

Si tu vois *« déjà lié à une autre machine »* → le code a déjà servi : **nouveau code** dans le CC.

---

## Étape 6 — Vie quotidienne

| Quand | Quoi |
|-------|------|
| Travail normal | App locale (ventes, stock…) — **sans Internet** |
| Internet dispo (idéalement chaque jour / semaine) | `php artisan desktop:heartbeat` |
| Après des ventes (quand online) | Les events iront dans l’outbox → `php artisan desktop:sync-out` |
| Nouvelle version publiée au CC | `php artisan desktop:updates-check --current=0.1.0` puis `desktop:updates-apply --current=0.1.0` |

Automatiser plus tard : `schedule-heartbeat.ps1` (tâches planifiées Windows).

**Note Phase 4 :** l’outbox se remplit surtout via `desktop:enqueue` / futur hook métier. Le POS n’écrit pas encore automatiquement chaque vente dans l’outbox — ça vient avec l’intégration métier.

---

## Étape 7 — Suivi côté Afroinov

| Où | Tu vois |
|----|---------|
| **Installations desktop** | Statut active, heartbeat, sync OUT |
| **Sync desktop** | Batches reçus (backup cloud) |
| **Updates desktop** | Versions publiées / yank |

Révoquer une machine volée / cassée → **Révoquer** sur l’install → anciens jetons invalides.

---

## Checklist “nouveau client gagné”

- [ ] Entreprise créée (type pharma)  
- [ ] Abonnement SaaS **actif**  
- [ ] Modules OK  
- [ ] Code d’activation émis + envoyé  
- [ ] PC client : PHP + install.ps1  
- [ ] `desktop:activate` OK  
- [ ] `desktop:heartbeat` OK  
- [ ] (option) Task Scheduler heartbeat/sync  
- [ ] Expliquer au client : reconnecter Internet au moins 1× / 2–3 semaines  

---

## Ce qui n’est **pas** encore le process “client final”

1. **Setup double-clic** (Inno + PHP embarqué) — squelette seulement  
2. **Remplacement atomique** de toute l’app à chaque update — staging/verify OK, packaging CI à durcir  
3. **OUT sync auto** depuis chaque vente Livewire — outbox + API prêts, hooks métier à brancher  
4. **IN sync / 2e PC** — Phase 5  

Pour un pilote interne / premier client technique, le process ci-dessus est le bon.

### Packaging “clé en main” (recommandé pour le terrain)

Sur ta machine de build :

```powershell
cd apps\pharma\deploy\windows
.\build-release.ps1 -Version 0.1.0 -ControlCenterUrl "https://TON-CC" -ZipPayload
.\compile-installer.ps1 -Version 0.1.0
```

Tu remets au client le `output\bproo-pharma-desktop-*-setup.exe` + le code d’activation.  
Détail : [PHASE-4-PACKAGING.md](./PHASE-4-PACKAGING.md) / [PACKAGING.md](../../apps/pharma/deploy/windows/PACKAGING.md).

Pour 50 pharmacies, brancher aussi les hooks outbox métier (ventes → sync auto).
