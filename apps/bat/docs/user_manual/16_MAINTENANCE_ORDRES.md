# 16 — Ordres de maintenance

> **Acteur :** 🟡 Responsable SAV / 🟢 Technicien  
> **Permission requise :** `maintenance.view`, `maintenance.create`, `maintenance.dispatch`, `maintenance.close`  
> **Route :** Menu → Maintenance → Ordres

---

## Processus 16.1 — Créer un ordre de maintenance corrective (demande client)

### Objectif
Enregistrer une panne ou un dysfonctionnement signalé par un client et déclencher le processus d'intervention.

### Prérequis
- Client IMMO TRUST SA et contrat CMT00001 existants

### Étapes

**1. Menu → Maintenance → Ordres → Nouveau**

**2. Informations**

| Champ | Valeur de test |
|-------|---------------|
| Client | `IMMO TRUST SA` |
| Contrat lié | `CMT00001` |
| Titre | `Panne ascenseur — Tour Centrale` |
| Description | `L'ascenseur de la Tour Centrale est bloqué au 8ème étage. Signalé par le gardien à 14h30.` |
| Type | `Correctif` |
| Priorité | `Haute` |
| Signalé par | `M. Eto'o Patrick, Syndic` |
| Date/heure signalement | `18/05/2026 14h30` |
| Délai SLA | `18/05/2026 18h30` _(4h SLA urgence)_ |
| Adresse d'intervention | `Tour Centrale, Bastos, Yaoundé` |

**3. Enregistrer** → code `ORD00001`, statut `Ouvert`

### Résultat attendu
- L'ordre apparaît dans la liste avec priorité Haute (couleur rouge)
- Il est lié au contrat CMT00001 et au client IMMO TRUST SA

---

## Processus 16.2 — Dispatcher un ordre à un technicien

### Objectif
Affecter l'ordre de maintenance à un technicien disponible.

### Prérequis
- Permission `maintenance.dispatch`

### Étapes

**1. Maintenance → Ordres → ORD00001 → Dispatcher**

**2. Technicien affecté** : `Pierre Martin`

**3. Date/heure intervention planifiée** : `18/05/2026 à 16h00`

**4. Notes pour le technicien** : `Contacter le gardien à l'arrivée. Tel : +237 6 11 22 33 44`

**5. Statut → `Affecté`**

### Résultat attendu
- Pierre Martin reçoit une notification
- L'ordre apparaît dans son planning
- Le délai SLA est visible en temps réel

---

## Processus 16.3 — Démarrer une intervention

### Étapes

**1. Maintenance → ORD00001 → Démarrer**

**2. Heure de début** : `18/05/2026 16h10`

**3. Statut → `En cours`**

---

## Processus 16.4 — Clôturer un ordre de maintenance

### Objectif
Marquer l'intervention comme terminée après résolution du problème.

### Prérequis
- Permission `maintenance.close`

### Étapes

**1. Maintenance → ORD00001 → Clôturer**

**2. Informations de clôture**

| Champ | Valeur |
|-------|--------|
| Heure de fin | `18/05/2026 17h45` |
| Travaux réalisés | `Remplacement câble ascenseur bloqué. Test de fonctionnement OK sur 10 cycles.` |
| Pièces remplacées | `Câble acier ø8mm, 12 mètres` |
| Observations | `Recommandation : révision complète moteur dans 6 mois` |

**3. SLA respecté ?**
- SLA deadline : 18h30 → Fin réelle : 17h45 → ✅ **Dans les délais**

**4. Statut → `Terminé`**

### Résultat attendu
- L'ordre est clôturé avec tous les détails d'intervention
- Durée réelle enregistrée : 1h35
- Apparaît dans le rapport technicien (Rapports → Rapport techniciens) pour Pierre Martin

---

## Processus 16.5 — Créer un ordre de maintenance préventive manuellement

| Champ | Valeur |
|-------|--------|
| Titre | `Visite préventive T2 2026 — Tour Centrale` |
| Type | `Préventif` |
| Priorité | `Normale` |
| Contrat lié | `CMT00001` |
| Date planifiée | `15/04/2026` |
| Technicien | `Pierre Martin` |

---

## Processus 16.6 — Traiter une urgence hors contrat

### Scénario
Un client sans contrat appelle pour une urgence (fuite d'eau).

### Étapes

**1. Maintenance → Ordres → Nouveau**

**2. Client** : `BIMEX SARL` _(pas de contrat maintenance)_

**3. Contrat lié** : laisser vide

**4. Type** : `Urgence`

**5. Priorité** : `Critique`

**6. Dispatcher immédiatement** → technicien le plus proche

> **Note facturation :** Créer une facture manuelle après clôture (tarif horaire hors contrat)

---

## Données de test — 3 ordres à créer

| Code | Client | Type | Priorité | Technicien | Statut |
|------|--------|------|----------|------------|--------|
| ORD00001 | IMMO TRUST SA | Correctif | Haute | Pierre Martin | Terminé |
| ORD00002 | IMMO TRUST SA | Préventif | Normale | Pierre Martin | Affecté |
| ORD00003 | BIMEX SARL | Urgence | Critique | Jean Dupont | En cours |
