---
title: "Guide Super Admin — Inov-Com ERP"
author: "Inov-Com"
date: "Juin 2026"
---

# Guide Super Admin — Inov-Com ERP

**Plateforme multi-vendeurs · Administration système**

| | |
|---|---|
| **Public** | Administrateurs plateforme (Super Admin) |
| **URL production** | https://erp.afroinov.com/admin |
| **Version** | Branche courante — Juin 2026 |

---

## Table des matières

1. Rôle du Super Admin
2. Connexion
3. Tableau de bord
4. Gestion des vendeurs
5. Santé et provisionnement
6. Packages (modules)
7. Plans d'abonnement
8. Abonnements vendeurs
9. Multi-magasin
10. Registre et événements modules
11. Ordre d'activation recommandé
12. Mises à jour serveur
13. Dépannage Super Admin

---

## 1. Rôle du Super Admin

Le Super Admin gère la **plateforme centrale**. Il ne saisit pas les ventes ni les stocks : il crée les vendeurs (boutiques), active les modules, gère les abonnements et surveille la santé technique.

| Niveau | Qui | Accès |
|--------|-----|-------|
| **Plateforme** | Super Admin | `/admin` — tous les vendeurs |
| **Vendeur** | Admin boutique | `/app?tenant=CODE` — une boutique |

Chaque vendeur possède :

- Un **code** unique (`demo`, `itc`…)
- Une **base PostgreSQL isolée**
- Des **modules activables** individuellement

---

## 2. Connexion

| Élément | Valeur |
|---------|--------|
| URL | `https://erp.afroinov.com/admin/login` |
| Compte initial (install) | `admin@demo.invo` |
| Mot de passe initial | `password` |

**Sécurité :** changez le mot de passe dès la première connexion.

Après connexion, le menu affiche : **Admin · Vendeurs · Plans · Packages · Modules · Santé · Events**

### Différence avec le compte vendeur

| Compte | URL | Créé où |
|--------|-----|---------|
| Super Admin | `/admin/login` | Seed installation |
| Admin boutique | `/app/login?tenant=CODE` | Formulaire **Créer vendeur** |

---

## 3. Tableau de bord

**Menu : Admin** → `/admin`

Indicateurs affichés :

- Nombre total de vendeurs
- Vendeurs actifs / en provisionnement / en échec
- Modules enregistrés dans le catalogue
- Derniers événements d'installation de modules

Utilisez ce tableau de bord pour repérer rapidement un vendeur bloqué en provisionnement.

---

## 4. Gestion des vendeurs

**Menu : Vendeurs** → `/admin/tenants`

### 4.1 Créer un vendeur

**Menu : Créer vendeur** → `/admin/tenants/create`

| Champ | Description |
|-------|-------------|
| Nom du vendeur | Nom affiché (ex. « Boutique Centrale ») |
| Code vendeur | Identifiant court, sans espace (ex. `demo`, `itc`) |
| Type d'activité | retail, pharmacie, boulangerie, restaurant, autre |
| Contact clé | Nom, téléphone, adresse (optionnel) |
| Admin boutique | Nom, e-mail, mot de passe du **premier utilisateur** |
| Multi-magasin | Cocher si plusieurs points de vente |

**Base de données :** créée automatiquement (`erp_{code}_xxxx`). Ne remplissez **pas** les champs PostgreSQL.

**Procédure :**

1. Remplir le formulaire et **Enregistrer**
2. Ouvrir **Santé vendeurs** (`/admin/tenants/health`)
3. Attendre le statut **OK** (1 à 2 minutes)
4. En cas d'échec → bouton **Relancer**

5. Aller dans **Packages** → installer les modules nécessaires
6. Communiquer au client :
   - URL : `https://erp.afroinov.com/app/login?tenant=CODE`
   - E-mail et mot de passe admin saisis à l'étape 4

### 4.2 Modifier un vendeur

`/admin/tenants/{code}/edit` — nom, contact, activation, type. La base n'est pas recréée.

### 4.3 Paramètres vendeur

`/admin/tenants/{code}/settings`

- Devise (XOF), locale (fr), fuseau horaire
- Taux TVA par défaut
- Préfixe factures
- Activation **multi-magasin** (lance la configuration automatique des boutiques)

### 4.4 Supprimer un vendeur

Bouton **Supprimer** dans la liste.

Supprime l'enregistrement plateforme et les liens modules. **La base PostgreSQL n'est pas supprimée automatiquement** — nettoyage manuel sur le serveur si nécessaire.

---

## 5. Santé et provisionnement

**Menu : Santé** → `/admin/tenants/health`

| Statut | Signification | Action |
|--------|---------------|--------|
| **OK** | Base accessible, provisionnement terminé | Aucune |
| **En cours** | Job de provisionnement en file | Attendre 1–2 min, Rafraîchir |
| **Erreur** | Échec (DB, migrations…) | Lire le message, cliquer **Relancer** |

Le bouton **Relancer** relance le provisionnement **sans commande SSH**.

**Prérequis serveur (une fois) :** variables `DB_PROVISION_*` dans `.env.production` pour la création auto des bases. Voir `deploy/docker/DEPLOY.md`.

---

## 6. Packages (modules)

**Menu : Packages** → `/admin/packages`

### Procédure d'installation

1. **Sélectionner le vendeur** dans la liste déroulante (obligatoire)
2. Rechercher un module si besoin
3. Cliquer **Installer** — installation immédiate (migrations + données initiales)
4. Le statut passe à **Installé** ; le menu apparaît côté vendeur

### Actions disponibles

| Bouton | Effet |
|--------|-------|
| **Installer** | Active le module pour le vendeur sélectionné |
| **Désinstaller** | Désactive (données conservées en base) |
| **Pour tous** | Installe pour **tous** les vendeurs existants |
| **Synchroniser les modules** | Met à jour le catalogue depuis la config |
| **Débloquer** | Efface un état « En cours… » bloqué |

### Statuts affichés

- **Actif (core)** — Utilisateurs, Configuration (toujours actifs)
- **Installé** — module activé pour ce vendeur
- **Non installé** — module disponible mais pas activé

### Règle importante

**Un seul module par famille** peut être actif (ex. une seule variante « Ventes »).

### Alternative : Modules par vendeur

`/admin/tenants/modules` — bascule ON/OFF par module. Même effet que Packages.

---

## 7. Plans d'abonnement

**Menu : Plans** → `/admin/plans`

| Champ | Description |
|-------|-------------|
| Nom / slug | Libellé commercial |
| Prix | Montant en FCFA |
| Intervalle | Mensuel, annuel… |
| **Démo** | Plan jamais suspendu automatiquement |
| Ordre | Position dans les listes |

Créer au minimum :

- Un plan **Standard** (payant)
- Un plan **Démo / Essai** (gratuit, `is_demo`)

---

## 8. Abonnements vendeurs

**Menu :** depuis la fiche vendeur → **Abonnement**  
URL : `/admin/tenants/{code}/subscription`

### Enregistrer un paiement

1. Ouvrir la fiche abonnement du vendeur
2. Saisir montant, date, mode de paiement
3. Le solde ou la période est mise à jour

### Appliquer le solde

Si le vendeur a un **solde créditeur**, l'appliquer pour prolonger l'abonnement.

### Changer de plan

Sélectionner un nouveau plan — la période est recalculée.

### Conséquence d'un abonnement expiré

Les utilisateurs du vendeur sont redirigés vers `/app/subscription?tenant=CODE`. Seules Connexion, Déconnexion et Abonnement restent accessibles.

---

## 9. Multi-magasin

### Activation (Super Admin)

- Cocher **Multi-magasin** à la **création** du vendeur, ou
- Activer dans **Paramètres vendeur** → lance un job de configuration

### Côté vendeur (après activation)

L'admin boutique configure les boutiques dans **Configuration → Gestion des boutiques** :

- Créer les points de vente
- Affecter les utilisateurs à une boutique
- Définir la boutique par défaut

---

## 10. Registre et événements modules

### Registre (`/admin/modules`)

Catalogue technique (clé, libellé). Maintenance avancée — l'activation se fait via **Packages**.

### Événements (`/admin/module-events`)

Historique install / uninstall par vendeur, date, utilisateur.

**Export** : bouton export ou `/admin/module-events/export`

---

## 11. Ordre d'activation recommandé

### Commerce de détail (retail)

```
1. Articles
2. Stock
3. Clients
4. Ventes
5. Caisse
6. Fournisseurs → Achats
7. Devis → Facturation → Paiements factures
8. Dépenses → Rapports
```

### Pharmacie

```
1. Articles → Stock → Lots → Ordonnances → Ventes
2. + modules commerce selon besoin
```

### Checklist mise en service d'un nouveau vendeur

| # | Action Super Admin |
|---|-------------------|
| 1 | Créer vendeur + attendre Santé OK |
| 2 | Installer modules (Packages) |
| 3 | Créer / assigner abonnement |
| 4 | Transmettre URL + identifiants admin boutique |
| 5 | Vérifier connexion client sur `/app/login?tenant=CODE` |

Le paramétrage métier (logo, articles, caissiers) est fait par l'**admin boutique**.

---

## 12. Mises à jour serveur

Sur le VPS, après chaque release :

```bash
cd ~/apps/erp
COMPOSE_PROJECT_NAME=erp TENANT_CODE=demo bash deploy/docker/deploy-update.sh
```

Remplacez `demo` par le code vendeur pour les migrations tenant.

Guide complet : `deploy/docker/DEPLOY.md`

---

## 13. Dépannage Super Admin

| Problème | Solution |
|----------|----------|
| Vendeur en **Erreur** (Santé) | Lire message → **Relancer** ; vérifier `DB_PROVISION_*` sur serveur |
| Packages affiche **Installer** alors que actif | Rafraîchir la page (correctif cache) |
| Module absent côté vendeur | Packages → Installer pour le bon vendeur |
| Client ne peut pas se connecter | Vérifier abonnement actif ; URL avec `?tenant=CODE` |
| Provisionnement long | Normal 1–2 min ; vérifier conteneur `erp-queue-1` |
| Suppression vendeur incomplète | Base PostgreSQL peut subsister — nettoyage manuel serveur |
| 502 sur erp.afroinov.com | Voir `deploy/docker/DEPLOY.md` section Dépannage |

### URLs utiles (production)

| Page | URL |
|------|-----|
| Admin login | https://erp.afroinov.com/admin/login |
| Vendeurs | https://erp.afroinov.com/admin/tenants |
| Packages | https://erp.afroinov.com/admin/packages |
| Santé | https://erp.afroinov.com/admin/tenants/health |
| Login vendeur | https://erp.afroinov.com/app/login?tenant=CODE |

---

**Inov-Com ERP** — Guide Super Admin · Document généré depuis la version courante du système.
