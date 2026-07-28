# 17 — Interventions terrain (Technicien)

> **Acteur :** 🟢 Technicien terrain  
> **Permission requise :** `maintenance.view`, `maintenance.create`  
> **Route :** Menu → Maintenance → Interventions

---

## Qu'est-ce qu'une intervention ?
Une **intervention** est le compte-rendu détaillé d'une action de terrain réalisée dans le cadre d'un ordre de maintenance. Un ordre peut avoir plusieurs interventions (si plusieurs techniciens ou plusieurs passages).

---

## Processus 17.1 — Créer le rapport d'une intervention

### Objectif
Documenter le travail réalisé sur site, les matériaux utilisés et la durée.

### Prérequis
- Ordre ORD00001 existant et affecté à Pierre Martin

### Étapes

**1. Menu → Maintenance → Interventions → Nouvelle**

_Ou depuis l'ordre : ORD00001 → Ajouter une intervention_

**2. Informations**

| Champ | Valeur de test |
|-------|---------------|
| Ordre de maintenance | `ORD00001 — Panne ascenseur Tour Centrale` |
| Technicien | `Pierre Martin` _(pré-rempli si connecté)_ |
| Statut | `Réalisé` |
| Heure planifiée | `18/05/2026 16h00` |
| Heure de début | `18/05/2026 16h10` |
| Heure de fin | `18/05/2026 17h45` |
| Durée | `95 minutes` _(calculée automatiquement)_ |

**3. Rapport de travaux**

| Champ | Contenu |
|-------|---------|
| Travaux réalisés | `Diagnostic : câble de traction bloqué dans rail guide. Remplacement câble ø8mm, 12ml. Test fonctionnement : 10 cycles OK. Nettoyage local machinerie.` |
| Constatations | `Usure visible sur poulies de renvoi. Rouille début sur rail est. À surveiller.` |
| Prochaine action | `Révision complète moteur recommandée dans 6 mois. Lubrification rails à prévoir dans 3 mois.` |

**4. Matériaux utilisés**

| Article | Quantité | Prix unitaire |
|---------|----------|--------------|
| Câble acier ø8mm | 12 m | 3 500 XOF/m |
| Graisse lubrifiante | 0.5 kg | 8 000 XOF/kg |

**5. Enregistrer** → durée calculée : 95 min

---

## Processus 17.2 — Obtenir la signature client sur place

### Objectif
Faire valider l'intervention par le client ou son représentant présent sur site.

### Étapes

**1. Intervention → zone Signature client**

**2. Montrer l'écran au client** (tablette recommandée)

**3. Le client signe** dans le cadre de signature digitale

**4. Nom du signataire** : `M. Eto'o Patrick, Syndic IMMO TRUST`

**5. Enregistrer**

### Résultat attendu
- La signature est enregistrée (image base64)
- La date/heure de signature est horodatée
- Le PV d'intervention peut être imprimé/exporté

---

## Processus 17.3 — Prendre des photos de l'intervention

### Objectif
Documenter visuellement l'avant/après de la réparation.

### Étapes

**1. Intervention → section Photos**

**2. Ajouter photo 1** : `Câble usé — avant remplacement`

**3. Ajouter photo 2** : `Câble neuf posé — après remplacement`

**4. Ajouter photo 3** : `Machinerie nettoyée`

---

## Processus 17.4 — Consulter le rapport technicien pour évaluation

### Objectif
Mesurer la performance terrain d'un technicien sur une période.

### Étapes

**1. Menu → Rapports → onglet Rapport techniciens**

**2. Sélectionner l'année** : `2026`

**3. Ligne Pierre Martin**

| Métrique | Valeur attendue |
|----------|----------------|
| Total interventions | 3 |
| Réalisées | 2 |
| Durée totale | ~3h |
| Durée moyenne | ~1h30 |
| Hors SLA | 0 |
| Taux SLA | 0% _(vert)_ |

**4. Exporter CSV** pour entretien annuel ou rapport direction
