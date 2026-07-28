# 01 — Onboarding plateforme (Admin)

> **Acteur :** 🔴 Administrateur plateforme  
> **Durée estimée :** 15 à 30 minutes par nouveau tenant  
> **Modules concernés :** Admin système (hors tenant)

---

## Processus 1.1 — Créer un nouveau tenant (entreprise)

### Objectif
Intégrer une nouvelle entreprise cliente sur la plateforme BPROO ERP, provisionner sa base de données et créer son premier administrateur.

### Prérequis
- Être connecté en tant qu'administrateur plateforme (`/admin`)
- Avoir les informations de l'entreprise cliente et son administrateur initial

### Étapes

**1. Accéder au formulaire de création**
- Menu sidebar → **Entreprises**
- Cliquer sur **Nouveau**

**2. Remplir les informations de base**

| Champ | Valeur de test |
|-------|---------------|
| Nom de l'entreprise | `KREOBAT SARL` |
| Code entreprise | `kreobat` _(unique, minuscules, sans espaces)_ |
| Nom base de données | `bproo_kreobat` |
| DB Host | laisser vide _(utilise .env par défaut)_ |
| DB Port | laisser vide |
| DB Username | laisser vide |
| DB Password | laisser vide |
| Actif | ✅ coché |

**3. Choisir le plan d'abonnement**

| Champ | Valeur de test |
|-------|---------------|
| Plan | `Starter` |
| Expiration plan | `31/12/2026` |
| Fin période d'essai | laisser vide |

**4. Remplir l'administrateur initial**

| Champ | Valeur de test |
|-------|---------------|
| Nom admin | `Admin Kreobat` |
| Email admin | `admin@kreobat.cm` |
| Mot de passe | `Admin2026!` |

**5. Créer**
- Cliquer sur **Créer**
- Message : _"Entreprise créée. Le provisionnement est en cours..."_

### Résultat attendu
- La ligne apparaît dans la liste avec statut **En attente** → **OK** (après job queue)
- Le badge plan affiche **Starter**
- La base `bproo_kreobat` est créée avec toutes les tables tenant

### Vérification
- Accéder à `/app/login?tenant=kreobat`
- Se connecter avec `admin@kreobat.cm` / `Admin2026!`
- Le dashboard tenant doit s'afficher

---

## Processus 1.2 — Activer / désactiver des modules pour un tenant

### Objectif
Contrôler quelles fonctionnalités sont disponibles pour une entreprise donnée.

### Prérequis
- Tenant créé et provisionné

### Étapes

**1. Accéder aux modules du tenant**
- Menu sidebar → **Modules** (icône puzzle)
- Ou : Liste entreprises → ligne kreobat → **Modules tenant**

**2. Activer un module optionnel**
- Repérer le module **Articles** (désactivé par défaut)
- Basculer le toggle sur **Actif**
- Confirmer

**3. Désactiver un module non utilisé**
- Repérer un module actif (ex. **Achats**)
- Basculer sur **Inactif**

### Résultat attendu
- Le menu sidebar du tenant reflète immédiatement les modules actifs
- Les routes des modules inactifs renvoient une erreur d'accès

---

## Processus 1.3 — Modifier le plan d'abonnement d'un tenant

### Objectif
Mettre à jour le plan ou prolonger l'abonnement d'une entreprise.

### Étapes

**1. Liste entreprises** → ligne kreobat → **Modifier**

**2. Section Abonnement**
- Changer Plan : `Starter` → `Pro`
- Mettre à jour Expiration plan : `31/12/2027`

**3. Mettre à jour** → vérifier le badge dans la liste

### Résultat attendu
- Colonne Plan affiche **Pro** dans la liste
- Page Analytique (`/admin/analytics`) met à jour la répartition

---

## Processus 1.4 — Surveiller la santé des tenants

### Objectif
Détecter les tenants en erreur de provisionnement ou en panne.

### Étapes

**1. Menu sidebar → Santé** (icône cœur)

**2. Lire le tableau**
- Colonne **Provisionnement** : `OK` / `En cours` / `Échec`
- Colonne **DB accessible** : connexion testée en live

**3. En cas d'échec**
- Lire le message d'erreur affiché
- Corriger les paramètres DB → **Modifier** le tenant
- Reprovisioner si nécessaire

### Résultat attendu
- Tous les tenants actifs affichent **OK** en provisionnement
- Aucun tenant en **Échec** non traité

---

## Processus 1.5 — Consulter les événements modules

### Objectif
Auditer les activations/désactivations de modules sur toute la plateforme.

### Étapes

**1. Menu sidebar → Événements** (icône calendrier)

**2. Filtrer** par tenant ou par module

**3. Exporter** en CSV si nécessaire

### Résultat attendu
- Toutes les actions d'activation module sont tracées avec horodatage et tenant
