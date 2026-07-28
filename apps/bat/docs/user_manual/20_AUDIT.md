# 20 — Journal d'audit & traçabilité

> **Acteur :** 🟠 Direction / 🟠 Admin tenant  
> **Permission requise :** `audit.view`  
> **Route :** Menu → Audit (icône bouclier)

---

## Qu'est-ce que le journal d'audit ?
Le journal d'audit enregistre **automatiquement** toute création, modification et suppression sur les entités sensibles du système (clients, devis, factures, projets, contrats…). Aucune action manuelle n'est nécessaire — c'est le trait `Auditable` du kernel qui écrit chaque entrée de façon transparente.

---

## Entités tracées automatiquement

| Entité | Table |
|--------|-------|
| Clients | `clients` |
| Offres | `offers` |
| Devis | `quotes` |
| Projets | `projects` |
| Factures | `invoices` |
| Bons de commande | `purchase_orders` |
| Contrats de maintenance | `maintenance_contracts` |
| Ordres de maintenance | `maintenance_orders` |
| Interventions | `interventions` |
| Documents | `documents` |

---

## Processus 20.1 — Consulter le journal d'audit

### Objectif
Savoir qui a modifié quoi et quand dans le système.

### Étapes

**1. Menu → Audit** (visible uniquement avec la permission `audit.view`)

**2. Le journal affiche les 30 derniers jours par défaut**

**3. Lire les colonnes**

| Colonne | Description |
|---------|-------------|
| Date/Heure | Horodatage précis de l'action |
| Entité | Table concernée (ex. `quotes`, `invoices`) |
| ID | Identifiant de l'enregistrement |
| Événement | `created` / `updated` / `deleted` / `status_changed` |
| Utilisateur | Qui a fait l'action (`null` = Système) |
| Modifications | Champ par champ : ~~ancienne valeur~~ → **nouvelle valeur** |

---

## Processus 20.2 — Filtrer le journal pour une investigation

### Scénario A — Qui a modifié ce devis ?

**1. Audit → filtre Entité → `quotes`**

**2. Filtre ID** ou chercher par plage de dates

**3. Lire l'événement `updated`** et identifier l'utilisateur

---

### Scénario B — Quelles actions a faites cet utilisateur aujourd'hui ?

**1. Audit → filtre Utilisateur → `Jean Dupont`**

**2. Filtre dates** : aujourd'hui uniquement

**3. Lire la liste** : créations, modifications, suppressions

---

### Scénario C — Qu'est-ce qui a été supprimé ce mois-ci ?

**1. Audit → filtre Événement → `deleted`**

**2. Filtre dates** : 1er du mois à aujourd'hui

**3. Identifier** les suppressions et leur auteur

---

### Scénario D — Tracer une modification suspecte de facture

**1. Audit → filtre Entité → `invoices`**

**2. Filtre Événement → `updated`**

**3. Chercher** la facture concernée par son ID

**4. Lire le diff**
- Champ `total_ttc` : ~~48 000 000~~ → **42 000 000** (modifié par Jean Dupont le 20/05/2026 à 14h32)

---

## Processus 20.3 — Lire un diff de modification

### Comment interpréter l'affichage des modifications

Pour l'événement `updated` :

```
total_ttc     ~~10 660 950~~   →  9 500 000
status        ~~draft~~        →  sent
sent_at       ~~(vide)~~       →  2026-05-18 09:14:00
```

- Texte ~~barré rouge~~ = valeur avant
- Texte **vert** = valeur après

Pour l'événement `created` :
- Affiche `N champ(s) initialisé(s)` — le nombre de champs renseignés à la création

Pour l'événement `deleted` :
- Affiche `N champ(s) supprimé(s)`

---

## Processus 20.4 — Réinitialiser les filtres

**1. Audit → Bouton Réinitialiser les filtres**

**2. Retour** à la vue 30 derniers jours, toutes entités, tous utilisateurs, tous événements

---

## Bonnes pratiques d'audit

| Fréquence | Action recommandée |
|-----------|-------------------|
| Quotidien | Vérifier les `deleted` de la journée |
| Hebdomadaire | Contrôler les modifications de factures (`invoices updated`) |
| Mensuel | Revue complète des actions par utilisateur |
| Lors d'un incident | Filtrer par entité + plage de date pour reconstituer la chronologie |

---

## Données de test pour alimenter le journal

Le journal se remplit **automatiquement** au fil des autres processus de test :
- Créer BIMEX SARL → 1 entrée `clients created`
- Créer DEV00001 → 1 entrée `quotes created`
- Envoyer DEV00001 → 1 entrée `quotes updated` (status: draft → sent)
- Accepter DEV00001 → 1 entrée `quotes updated` (status: sent → accepted)
- Créer FAC00001 → 1 entrée `invoices created`
- Payer FAC00001 → 1 entrée `invoices updated` (status → paid)

Après avoir réalisé tous les processus des manuels 06 à 18, le journal doit contenir **plus de 30 entrées**.
