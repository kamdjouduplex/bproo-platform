# 08 — Devis

> **Acteur :** 🟡 Commercial / 🟠 Admin tenant  
> **Permission requise :** `devis.view`, `devis.create`, `devis.edit`, `devis.send`, `devis.accept`  
> **Route :** Menu → Devis

---

## Processus 8.1 — Créer un devis

### Objectif
Établir un devis chiffré pour un client, avec lignes de travaux, prix unitaires, TVA et conditions.

### Prérequis
- Client BIMEX SARL créé (manuel 06)
- Catalogue articles créé (manuel 05) — optionnel mais recommandé

### Étapes

**1. Menu → Devis → Nouveau**

**2. En-tête du devis**

| Champ | Valeur de test |
|-------|---------------|
| Client | `BIMEX SARL` |
| Titre | `Construction siège social Akwa — Gros œuvre` |
| Date d'émission | _(date du jour)_ |
| Validité | `30 jours` |
| Conditions de paiement | `40% à la commande, 40% à mi-travaux, 20% à réception` |
| Notes | `Devis établi sur la base des plans fournis le 05/04/2026` |

**3. Ajouter des lignes**

| Description | Qté | Unité | PU HT | Total HT |
|-------------|-----|-------|-------|----------|
| Fondations en béton armé | 1 | Forfait | 4 500 000 | 4 500 000 |
| Élévation murs RDC (parpaing 15) | 320 | m² | 35 000 | 11 200 000 |
| Dalle de compression RDC | 180 | m² | 28 000 | 5 040 000 |
| Élévation murs R+1 | 320 | m² | 35 000 | 11 200 000 |
| Dalle R+1 | 180 | m² | 28 000 | 5 040 000 |
| Chape sol R+2 et R+3 | 360 | m² | 8 500 | 3 060 000 |
| Toiture (charpente + couverture) | 1 | Forfait | 8 500 000 | 8 500 000 |

**4. Vérifier les totaux**
- Total HT calculé automatiquement
- TVA 19,25% calculée automatiquement
- Total TTC affiché

**5. Enregistrer** → statut `Brouillon`, code auto : ex. `DEV00001`

### Résultat attendu
- Devis créé en brouillon, modifiable
- Visible dans l'historique de BIMEX SARL

---

## Processus 8.2 — Envoyer un devis au client

### Objectif
Valider le devis et le marquer comme envoyé pour démarrer le délai de validité.

### Prérequis
- Devis DEV00001 en statut `Brouillon`
- Permission `devis.send`

### Étapes

**1. Devis → DEV00001 → Voir**

**2. Bouton Envoyer** (ou modifier statut → `Envoyé`)

**3. Générer le PDF** pour envoi par email au client

### Résultat attendu
- Statut passe à `Envoyé`
- Date d'envoi enregistrée
- Le décompte de validité commence

---

## Processus 8.3 — Enregistrer la réponse du client

### Cas A — Client accepte

**1. Devis → DEV00001 → Accepter**

**2. Date d'acceptation** = date du jour

**3. Résultat**
- Statut → `Accepté`
- Bouton **Créer un projet** devient disponible
- Le devis n'est plus modifiable

### Cas B — Client refuse ou négocie

**1. Devis → DEV00001 → Refuser**

**2. Motif** : `Prix trop élevé — client demande révision`

**3. Options**
- Clôturer comme **Refusé**
- Ou dupliquer et créer un **DEV00002 révisé** avec remise

---

## Processus 8.4 — Dupliquer un devis pour révision

### Objectif
Proposer une version révisée sans repartir de zéro.

### Étapes

**1. Devis → DEV00001 → Dupliquer**

**2. Un DEV00002 est créé** en brouillon avec les mêmes lignes

**3. Modifier** : appliquer une remise globale de 5% ou supprimer des postes

**4. Envoyer** le nouveau devis

---

## Processus 8.5 — Suivre le pipeline devis

### Objectif
Avoir une vue d'ensemble de tous les devis en cours et calculer le taux de conversion.

### Étapes

**1. Menu → Rapports → onglet Pipeline devis**

**2. Sélectionner l'année**

**3. Lire**
- Entonnoir : Brouillons / Envoyés / Acceptés / Refusés
- Taux de conversion
- Valeur des devis acceptés

### Résultat attendu
- Taux de conversion visible (objectif : > 50%)
- Valeur pipeline "en attente de réponse" identifiée pour relances
