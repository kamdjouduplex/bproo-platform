# 19 — Rapports financiers & opérationnels

> **Acteur :** 🟠 Direction / 🟡 Comptable / 🟡 Chef de projet  
> **Permission requise :** Accès au module Reports  
> **Route :** Menu → Rapports

---

## Vue d'ensemble des 5 onglets

| Onglet | Audience | Objectif |
|--------|----------|---------|
| Vieillissement AR | Comptable | Identifier les impayés par ancienneté |
| Revenus | Direction | Suivre facturation et encaissement mensuel |
| Pipeline devis | Commercial | Mesurer le taux de conversion |
| Rentabilité projets | Direction / Chef de projet | Analyser marge par projet |
| Rapport techniciens | RH / Direction | Évaluer performance terrain |

---

## Processus 19.1 — Analyser les impayés (AR Aging)

### Objectif
Identifier rapidement quels clients ont des factures en retard et depuis combien de temps.

### Étapes

**1. Menu → Rapports → onglet Vieillissement AR**

**2. Lire les KPI cards**
- Total impayé global
- Répartition par bucket (0–30j / 31–60j / 61–90j / >90j)

**3. Lire le tableau client par client**
- Colonne **% > 60j** : si rouge, ce client est en zone de risque

**4. Prioriser les relances**
- Commencer par les clients dans le bucket **> 90j**
- Puis **61–90j**

**5. Exporter CSV** → transmettre au service recouvrement

### Données de test à créer

Créer les factures suivantes **sans les payer** pour alimenter le rapport :

| Facture | Client | Montant TTC | Émise le | Échue le |
|---------|--------|-------------|----------|----------|
| FAC00002 | BIMEX SARL | 10 660 950 | 31/05/2026 | 30/06/2026 |
| FAC00003 | IMMO TRUST SA | 4 350 000 | 15/04/2026 | 15/05/2026 |

→ FAC00003 sera dans le bucket **> 30j** (émise il y a plus de 30 jours)

---

## Processus 19.2 — Suivre les revenus mensuels

### Objectif
Visualiser la courbe de facturation et d'encaissement mois par mois.

### Étapes

**1. Rapports → onglet Revenus**

**2. Sélectionner la période** : 6 / 12 / 24 derniers mois

**3. Lire les KPI cards**
- Total facturé sur la période
- Total encaissé
- Taux d'encaissement (objectif : > 85%)
- Nombre de factures

**4. Bar chart** : barres violettes (facturé) vs barres vertes (encaissé)

**5. Tableau détail** : mois par mois avec taux d'encaissement et mini barre

**6. Exporter CSV** pour comptabilité analytique

### Indicateurs à surveiller
- Taux encaissement < 70% → problème recouvrement
- Facturé en hausse mais encaissé stable → retards de paiement croissants

---

## Processus 19.3 — Analyser le pipeline commercial

### Objectif
Mesurer l'efficacité commerciale et identifier les devis à relancer.

### Étapes

**1. Rapports → onglet Pipeline devis**

**2. Sélectionner l'année** : `2026`

**3. KPI cards**
- Total devis émis
- Acceptés + taux de conversion
- Valeur signée
- En attente de réponse (relances possibles)

**4. Entonnoir** : visualiser où les devis se bloquent

**5. Tableau mensuel** : mois où les acceptations se concentrent

### Résultat attendu (avec données de test)
- 1 devis accepté (DEV00001) sur 2 émis → taux conversion 50%
- Valeur signée : 48 000 000 XOF

---

## Processus 19.4 — Analyser la rentabilité des projets

### Objectif
Identifier les projets rentables, les projets à risque et la marge globale de l'entreprise.

### Étapes

**1. Rapports → onglet Rentabilité projets**

**2. KPI cards globaux**
- Budget total / Coût réel / Encaissé / **Marge globale**
- Marge globale en % (objectif : > 20%)

**3. Tableau projet par projet**

| Indicateur | Signe positif | Signe négatif |
|------------|--------------|--------------|
| Marge % | > 20% (vert) | < 0% (rouge) |
| Budget dépassé | Non | Oui (⚠ rouge) |
| Avancement | ≥ 80% | < 30% (projet qui traîne) |

**4. Cas particuliers à identifier**
- Projet avec marge négative → analyser les achats et main d'œuvre
- Projet avec coût réel > budget → renégocier avec le client ou couper les dépenses

**5. Exporter CSV** pour présentation direction

### Données de test attendues

| Projet | Budget | Coût réel | Encaissé | Marge |
|--------|--------|-----------|----------|-------|
| PRJ00001 | 48M | 4,8M (en cours) | 27,6M | +22,8M _(positif car acompte reçu)_ |
| PRJ00002 | 12,5M | 4,5M | 0 | -4,5M _(pas encore facturé)_ |

---

## Processus 19.5 — Évaluer les performances des techniciens

### Objectif
Comparer les techniciens : volume d'interventions, durée moyenne, respect du SLA.

### Étapes

**1. Rapports → onglet Rapport techniciens**

**2. Sélectionner l'année** : `2026`

**3. KPI globaux**
- Nb techniciens actifs / Total interventions / Heures terrain
- Taux hors SLA global (rouge si > 20%)

**4. Tableau par technicien**
- Barre de taux de réalisation (vert = bon)
- Durée moyenne par intervention
- Badge SLA : vert (0%) / orange (1–20%) / rouge (>20%)

**5. Exporter CSV** pour entretien annuel ou rapport RH

### Résultat attendu (avec données de test)

| Technicien | Total | Réalisées | Durée moy. | Hors SLA |
|------------|-------|-----------|------------|----------|
| Pierre Martin | 3 | 2 | 1h35 | 0 (vert) |
