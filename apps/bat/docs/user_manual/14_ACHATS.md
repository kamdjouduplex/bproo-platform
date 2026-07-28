# 14 — Achats (Bons de commande)

> **Acteur :** 🟡 Responsable achats / 🟠 Admin tenant  
> **Permission requise :** `achats.view`, `achats.create`, `achats.edit`, `achats.approve`  
> **Route :** Menu → Achats

---

## Processus 14.1 — Créer un bon de commande fournisseur

### Objectif
Commander des matériaux ou services auprès d'un fournisseur pour un chantier.

### Prérequis
- Projet PRJ00001 actif
- Fournisseur connu (nom et coordonnées)

### Étapes

**1. Menu → Achats → Nouveau**

**2. En-tête**

| Champ | Valeur de test |
|-------|---------------|
| Fournisseur | `Cimencam Douala` |
| Projet | `PRJ00001` |
| Titre | `Commande ciment et agrégats — Mai 2026` |
| Date de commande | `10/05/2026` |
| Date de livraison souhaitée | `13/05/2026` |
| Conditions de paiement | `30 jours` |

**3. Lignes de commande**

| Désignation | Qté | Unité | PU HT | Total HT |
|-------------|-----|-------|-------|----------|
| Ciment Portland CEM I 42.5 | 200 | Sacs | 8 500 | 1 700 000 |
| Sable de rivière | 15 | m³ | 35 000 | 525 000 |
| Gravier 15/25 | 10 | m³ | 45 000 | 450 000 |

- **Total HT** : 2 675 000 XOF
- **TVA** : 514 937 XOF
- **Total TTC** : 3 189 937 XOF

**4. Notes** : `Livraison chantier Akwa — contact chef chantier : +237 6 55 44 33 22`

**5. Enregistrer** → code `BC00001`, statut `Brouillon`

---

## Processus 14.2 — Soumettre un bon de commande pour approbation

### Objectif
Faire valider la dépense par le responsable avant envoi au fournisseur.

### Étapes

**1. Achats → BC00001 → Soumettre**

**2. Statut → `En attente d'approbation`**

---

## Processus 14.3 — Approuver un bon de commande

### Prérequis
- Permission `achats.approve`

### Étapes

**1. Achats → liste → filtrer par statut `En attente`**

**2. Cliquer sur BC00001 → Approuver**

**3. Statut → `Approuvé`**

**4. Le bon peut maintenant être envoyé au fournisseur** (PDF)

---

## Processus 14.4 — Enregistrer la réception de marchandises

### Objectif
Confirmer que la livraison est bien arrivée sur le chantier.

### Étapes

**1. Achats → BC00001 → Réceptionner**

**2. Vérifier les quantités reçues** vs commandées

| Article | Commandé | Reçu | Écart |
|---------|----------|------|-------|
| Ciment | 200 sacs | 200 sacs | 0 |
| Sable | 15 m³ | 14 m³ | -1 m³ (à noter) |
| Gravier | 10 m³ | 10 m³ | 0 |

**3. Note** : `Sable : 1m³ manquant — fournisseur à rappeler pour livraison complémentaire`

**4. Statut → `Réceptionné` (partiel) ou `Réceptionné (complet)`**

---

## Processus 14.5 — Refuser un bon de commande

### Objectif
Bloquer un achat non justifié ou en dehors du budget.

### Étapes

**1. Achats → BC00001 → Refuser**

**2. Motif** : `Dépassement budget matériaux prévu. Revoir quantités.`

**3. Statut → `Refusé`**

**4. Le demandeur doit créer un nouveau BC corrigé**

---

## Processus 14.6 — Suivre les achats d'un projet

### Objectif
Contrôler les engagements de dépenses par rapport au budget projet.

### Étapes

**1. Achats → filtrer par Projet → `PRJ00001`**

**2. Total des BC approuvés** = engagement de dépenses

**3. Comparer au champ `Budget` du projet** (onglet Projets → PRJ00001)

**4. Rapports → Rentabilité projets** → voir coût réel vs budget

---

## Données de test — 3 bons de commande à créer

| Code | Fournisseur | Projet | Montant TTC | Statut |
|------|-------------|--------|-------------|--------|
| BC00001 | Cimencam Douala | PRJ00001 | 3 189 937 | Approuvé |
| BC00002 | Acier Plus SARL | PRJ00001 | 5 850 000 | Réceptionné |
| BC00003 | Location Engins CM | PRJ00002 | 1 250 000 | Brouillon |
