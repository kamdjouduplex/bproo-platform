# 04 — Utilisateurs & Droits

> **Acteur :** 🟠 Admin tenant  
> **Permission requise :** `users.view`, `users.create`, `users.edit`  
> **Route :** Menu → Utilisateurs

---

## Processus 4.1 — Créer un utilisateur

### Objectif
Ajouter un collaborateur (commercial, technicien, comptable) au portail de l'entreprise.

### Prérequis
- Être connecté en tant qu'Admin tenant

### Étapes

**1. Menu → Utilisateurs → Nouveau**

**2. Remplir le formulaire**

| Champ | Valeur de test (commercial) |
|-------|---------------------------|
| Nom | `Jean Dupont` |
| Email | `j.dupont@kreobat.cm` |
| Mot de passe | `Jean2026!` |
| Rôle | `Utilisateur` _(à modifier après)_ |

**3. Enregistrer**

---

## Processus 4.2 — Attribuer un rôle à un utilisateur

### Objectif
Donner à un utilisateur les droits correspondant à sa fonction dans l'entreprise.

### Étapes

**1. Liste utilisateurs → Jean Dupont → Modifier**

**2. Champ Rôle** → sélectionner `Admin` ou `Utilisateur`

> Le rôle **Admin** donne accès à toutes les permissions.  
> Le rôle **Utilisateur** donne un accès de base, à affiner via la matrice.

**3. Enregistrer**

---

## Processus 4.3 — Configurer la matrice de permissions

### Objectif
Définir précisément ce que chaque rôle peut faire : voir, créer, modifier, supprimer, approuver.

### Permissions disponibles par module

| Module | Permissions |
|--------|------------|
| Clients | `clients.view`, `clients.create`, `clients.edit`, `clients.delete` |
| Offres | `offres.view`, `offres.create`, `offres.edit`, `offres.delete` |
| Devis | `devis.view`, `devis.create`, `devis.edit`, `devis.delete`, `devis.send`, `devis.accept` |
| Projets | `projets.view`, `projets.create`, `projets.edit`, `projets.delete` |
| Facturation | `facturation.view`, `facturation.create`, `facturation.edit`, `facturation.send` |
| Achats | `achats.view`, `achats.create`, `achats.approve` |
| Maintenance | `maintenance.view`, `maintenance.create`, `maintenance.dispatch`, `maintenance.close` |
| Suivi terrain | `suivi.view`, `suivi.create`, `suivi.edit`, `suivi.validate` |
| Planning | `planning.view`, `planning.create`, `planning.edit`, `planning.delete` |
| Documents | `dms.view`, `dms.create`, `dms.delete` |
| Audit | `audit.view` |
| Configuration | `configuration.view`, `configuration.edit` |

### Profils types recommandés

**Commercial (Jean Dupont)**
- `clients.*`, `offres.*`, `devis.view`, `devis.create`, `devis.send`

**Chef de projet (Marie Ngo)**
- `projets.*`, `planning.*`, `suivi.*`, `dms.*`

**Technicien terrain (Pierre Martin)**
- `maintenance.view`, `suivi.create`, `suivi.edit`, `planning.view`, `dms.create`

**Comptable (Alice Bello)**
- `facturation.*`, `achats.view`, `achats.approve`, `clients.view`

**Direction**
- Toutes les permissions + `audit.view`

---

## Processus 4.4 — Désactiver un utilisateur

### Objectif
Bloquer l'accès d'un collaborateur qui a quitté l'entreprise sans supprimer son historique.

### Étapes

**1. Liste utilisateurs → utilisateur cible → Modifier**

**2. Décocher la case Actif**

**3. Enregistrer**

### Résultat attendu
- L'utilisateur ne peut plus se connecter
- Ses données (devis, rapports) restent accessibles et consultables
