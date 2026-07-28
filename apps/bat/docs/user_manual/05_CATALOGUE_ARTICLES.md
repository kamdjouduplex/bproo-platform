# 05 — Catalogue Articles (Items)

> **Acteur :** 🟠 Admin tenant / 🟡 Gestionnaire  
> **Permission requise :** `items.view`, `items.create`, `items.edit`  
> **Route :** Menu → Articles  
> **Note :** Ce module doit être activé manuellement par l'admin plateforme (non activé par défaut)

---

## Processus 5.1 — Créer un article ou service

### Objectif
Constituer le catalogue de produits et services réutilisables dans les devis et factures.

### Prérequis
- Module **Articles** activé par l'admin plateforme pour ce tenant

### Étapes

**1. Menu → Articles → Nouveau**

**2. Remplir le formulaire**

| Champ | Exemple produit | Exemple service |
|-------|----------------|-----------------|
| Référence | `MAT-CIMENT-50` | `SERV-MAÇON-H` |
| Désignation | `Sac de ciment 50kg` | `Main d'œuvre maçonnerie (heure)` |
| Unité | `Sac` | `Heure` |
| Prix unitaire HT | `8 500` | `15 000` |
| Description | `Ciment Portland CEM I 42.5` | `Inclus outillage de base` |
| TVA applicable | `19.25%` | `19.25%` |

**3. Enregistrer**

### Résultat attendu
- L'article apparaît dans la liste avec sa référence et son prix
- Il est disponible dans les sélecteurs de lignes de devis et factures

---

## Processus 5.2 — Mettre à jour un tarif

### Objectif
Réviser les prix du catalogue suite à une variation des coûts fournisseurs.

### Étapes

**1. Menu → Articles → ligne article → Modifier**

**2. Modifier le Prix unitaire HT**

**3. Enregistrer**

### Cas particulier
- La modification ne rétroagit pas sur les devis et factures déjà créés
- Les nouveaux devis utiliseront le nouveau tarif

---

## Processus 5.3 — Organiser le catalogue par catégorie de travaux

### Objectif
Retrouver rapidement les articles par type (Matériaux, Main d'œuvre, Équipement, Sous-traitance).

### Données de test — Catalogue type BTP

| Référence | Désignation | Unité | Prix HT |
|-----------|-------------|-------|---------|
| `MAT-CIMENT-50` | Sac ciment 50kg | Sac | 8 500 |
| `MAT-SABLE-M3` | Sable de rivière | m³ | 35 000 |
| `MAT-FER-12` | Fer à béton ø12 | Barre | 12 000 |
| `MAT-PARPAING` | Parpaing 15x20x40 | Unité | 450 |
| `MAT-CARRELAGE` | Carrelage 60x60 cm | m² | 9 500 |
| `SERV-MAÇON-H` | Main d'œuvre maçonnerie | Heure | 3 500 |
| `SERV-ÉLECTR-H` | Main d'œuvre électricité | Heure | 4 500 |
| `SERV-PLOMB-H` | Main d'œuvre plomberie | Heure | 4 000 |
| `SERV-CARREL-M2` | Pose carrelage | m² | 5 500 |
| `EQUIP-BÉTON-J` | Location bétonnière/jour | Jour | 25 000 |
| `EQUIP-ÉCHAF-M2` | Location échafaudage | m² | 1 500 |
| `SOUS-ÉLEC-FO` | Sous-traitance électricité forfait | Forfait | Variable |
