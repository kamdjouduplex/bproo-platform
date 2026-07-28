# 13 — Facturation

> **Acteur :** 🟡 Comptable / 🟠 Admin tenant  
> **Permission requise :** `facturation.view`, `facturation.create`, `facturation.edit`, `facturation.send`  
> **Route :** Menu → Facturation

---

## Processus 13.1 — Créer une facture

### Objectif
Émettre une facture suite à des travaux réalisés ou en acompte sur un marché signé.

### Prérequis
- Client existant
- Projet ou devis associé (recommandé)

### Étapes

**1. Menu → Facturation → Nouveau**

**2. En-tête**

| Champ | Valeur de test (facture d'avancement) |
|-------|--------------------------------------|
| Client | `BIMEX SARL` |
| Projet | `PRJ00001` |
| Titre | `Situation N°2 — Travaux mois mai 2026` |
| Date d'émission | `31/05/2026` |
| Date d'échéance | `30/06/2026` _(30 jours)_ |
| Type | `Facture` |
| Conditions de paiement | `30 jours date de facture` |

**3. Lignes de facturation**

| Description | Qté | Unité | PU HT | Total HT |
|-------------|-----|-------|-------|----------|
| Travaux gros œuvre — Dalle R+1 coulée | 180 | m² | 28 000 | 5 040 000 |
| Élévation murs R+1 (nord + est) | 80 | ml | 35 000 | 2 800 000 |
| Main d'œuvre encadrement chantier | 22 | Jour | 50 000 | 1 100 000 |

**4. TVA** : 19,25% calculée automatiquement

**5. Vérifier le résumé**
- Total HT : 8 940 000 XOF
- TVA : 1 720 950 XOF
- **Total TTC : 10 660 950 XOF**

**6. Enregistrer** → `FAC00002` statut `Brouillon`

---

## Processus 13.2 — Émettre et envoyer une facture

**1. Facturation → FAC00002 → Envoyer**

**2. Statut → `Envoyé`**, date d'envoi enregistrée

**3. Générer le PDF** → transmettre au client par email ou courrier

### Résultat attendu
- La facture n'est plus modifiable
- Elle apparaît dans le rapport AR Aging (Rapports → Vieillissement AR) si impayée

---

## Processus 13.3 — Enregistrer un paiement

### Objectif
Marquer une facture comme partiellement ou totalement réglée.

### Étapes

**1. Facturation → FAC00002 → Enregistrer paiement**

| Champ | Valeur |
|-------|--------|
| Montant payé | `10 660 950 XOF` _(paiement intégral)_ |
| Mode de paiement | `Virement bancaire` |
| Date de paiement | `25/06/2026` |
| Référence | `VIR-2026-0892` |

**2. Enregistrer**

### Résultat attendu
- Statut → `Payé`
- Montant dû → `0`
- La facture disparaît du rapport AR Aging
- Le rapport Revenus (onglet Revenus) met à jour le montant encaissé

---

## Processus 13.4 — Gérer les paiements partiels

### Scénario
Le client BIMEX verse 6 000 000 XOF sur la FAC00002 de 10 660 950 XOF.

### Étapes

**1. Facturation → FAC00002 → Enregistrer paiement**
- Montant payé : `6 000 000`
- Mode : `Chèque`
- Date : `20/06/2026`

**2. Résultat**
- Statut → `Partiellement payé`
- Montant dû → `4 660 950 XOF`
- Apparaît dans l'AR Aging bucket 0–30j

**3. Quand le solde est versé**
- Enregistrer le 2e paiement : `4 660 950`
- Statut → `Payé`

---

## Processus 13.5 — Créer une facture d'acompte

### Objectif
Facturer l'acompte à la commande (souvent 30 à 40% du marché).

| Champ | Valeur |
|-------|--------|
| Titre | `Acompte 40% — Marché construction siège BIMEX` |
| Projet | `PRJ00001` |
| Ligne 1 | `Acompte 40% sur marché n° CTR-2026-041 — Prix HT : 48 000 000 XOF` |
| Quantité | `1` |
| PU HT | `19 200 000` _(40% de 48M)_ |

---

## Processus 13.6 — Émettre un avoir (avoir sur facture)

### Objectif
Annuler totalement ou partiellement une facture déjà envoyée (erreur, remise accordée).

### Étapes

**1. Facturation → FAC00002 → Créer un avoir**

**2. Type → `Avoir`**

**3. Référence avoir pour → `FAC00002`**

**4. Lignes** : lignes négatives correspondant à ce qui doit être annulé

**5. Enregistrer et envoyer**

---

## Processus 13.7 — Suivre les impayés (AR Aging)

### Objectif
Identifier les factures échues non réglées et calculer les relances prioritaires.

### Étapes

**1. Menu → Rapports → onglet Vieillissement AR**

**2. Lire le tableau**
- Bucket **0–30j** : récent, relance douce
- Bucket **31–60j** : relance ferme
- Bucket **> 90j** : mise en demeure ou contentieux

**3. Exporter CSV** pour transmission au service recouvrement

---

## Données de test — 3 factures à créer

| Code | Client | Titre | TTC | Statut |
|------|--------|-------|-----|--------|
| FAC00001 | BIMEX SARL | Acompte 40% marché | 27 592 000 | Payé |
| FAC00002 | BIMEX SARL | Situation N°2 mai 2026 | 10 660 950 | Envoyé |
| FAC00003 | IMMO TRUST SA | Travaux ravalement façade | 4 350 000 | Envoyé |
