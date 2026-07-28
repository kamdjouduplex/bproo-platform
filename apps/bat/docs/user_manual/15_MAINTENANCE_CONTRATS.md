# 15 — Contrats de maintenance

> **Acteur :** 🟠 Admin tenant / 🟡 Responsable SAV  
> **Permission requise :** `maintenance.view`, `maintenance.create`, `maintenance.edit`  
> **Route :** Menu → Maintenance → Contrats

---

## Qu'est-ce qu'un contrat de maintenance ?
Un contrat de maintenance est un **engagement SLA (Service Level Agreement)** entre l'entreprise et un client pour assurer l'entretien régulier de bâtiments ou équipements. Il définit la périodicité des visites, les délais d'intervention et les pénalités.

---

## Processus 15.1 — Créer un contrat de maintenance

### Objectif
Formaliser un contrat annuel de maintenance pour un client avec ses conditions SLA.

### Prérequis
- Client IMMO TRUST SA existant

### Étapes

**1. Menu → Maintenance → Contrats → Nouveau**

**2. Informations du contrat**

| Champ | Valeur de test |
|-------|---------------|
| Client | `IMMO TRUST SA` |
| Titre | `Contrat maintenance bâtiments Yaoundé 2026` |
| Référence | `CMT-2026-IMMO-001` |
| Type | `Préventif + Correctif` |
| Date de début | `01/01/2026` |
| Date de fin | `31/12/2026` |
| Montant annuel HT | `4 800 000 XOF` _(facturation mensuelle)_ |

**3. Conditions SLA**

| SLA | Valeur |
|-----|--------|
| Délai intervention urgence | `4 heures` |
| Délai intervention normale | `48 heures` |
| Visites préventives | `1 par trimestre` |
| Pénalité hors SLA | `1% du montant mensuel par jour de retard` |

**4. Périmètre**
- `Immeuble Tour Centrale, 12 étages, Bastos Yaoundé`
- `Immeuble Résidence Soleil, 6 étages, Mvan Yaoundé`

**5. Enregistrer** → code `CMT00001`

### Résultat attendu
- Contrat actif affiché dans la liste maintenance
- Les ordres de maintenance peuvent maintenant y être rattachés

---

## Processus 15.2 — Planifier les visites préventives (scheduler)

### Objectif
Générer automatiquement les ordres de maintenance préventifs pour toute l'année.

### Étapes

**1. Contrat CMT00001 → Voir → Générer visites préventives**

**2. Paramétrer**
- Fréquence : Trimestrielle
- Première visite : `15/01/2026`
- Technicien affecté : `Pierre Martin`

**3. Confirmer la génération**

### Résultat attendu
- 4 ordres de maintenance préventifs créés automatiquement
- Dates : 15/01, 15/04, 15/07, 15/10/2026
- Chacun lié au contrat CMT00001

---

## Processus 15.3 — Renouveler un contrat

### Objectif
Reconduire un contrat arrivant à échéance.

### Étapes

**1. Maintenance → Contrats → CMT00001 → Renouveler**

**2. Nouvelles dates**
- Début : `01/01/2027`
- Fin : `31/12/2027`

**3. Revalorisation** _(si applicable)_
- Nouveau montant : `5 280 000 XOF` (+10%)

**4. Enregistrer** → code `CMT00002` (nouveau contrat lié à l'ancien)

---

## Processus 15.4 — Résilier un contrat

### Étapes

**1. Maintenance → Contrats → CMT00001 → Modifier**

**2. Statut → `Résilié`**

**3. Date de résiliation** + motif

**4. Enregistrer**

### Résultat attendu
- Les futurs ordres préventifs ne sont plus générés
- L'historique des interventions passées reste consultable

---

## Données de test — 2 contrats à créer

| Code | Client | Type | Période | Montant annuel |
|------|--------|------|---------|---------------|
| CMT00001 | IMMO TRUST SA | Préventif + Correctif | 2026 | 4 800 000 XOF |
| CMT00002 | BIMEX SARL | Correctif | 2026 | 2 400 000 XOF |
