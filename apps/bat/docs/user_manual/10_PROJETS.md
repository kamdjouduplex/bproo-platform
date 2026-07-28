# 10 — Gestion des projets

> **Acteur :** 🟠 Admin tenant / 🟡 Chef de projet  
> **Permission requise :** `projets.view`, `projets.create`, `projets.edit`  
> **Route :** Menu → Projets

---

## Processus 10.1 — Créer un projet

### Objectif
Ouvrir un projet de construction ou rénovation et en assurer le suivi opérationnel.

### Prérequis
- Client existant
- Devis accepté (recommandé mais optionnel)

### Étapes

**1. Menu → Projets → Nouveau**

**2. Onglet Informations générales**

| Champ | Valeur de test |
|-------|---------------|
| Titre | `Rénovation villa Ayissi — Bastos` |
| Client | `M. Ayissi Jean-Paul` |
| Devis lié | _(optionnel)_ |
| Type de projet | `Rénovation` |
| Numéro de contrat | `CTR-2026-042` |
| Priorité | `Normale` |

**3. Onglet Planning**

| Champ | Valeur |
|-------|--------|
| Date de début | `01/05/2026` |
| Date de fin prévue | `31/08/2026` |
| Chef de projet | `Jean Dupont` |

**4. Onglet Financier**

| Champ | Valeur |
|-------|--------|
| Budget interne | `12 500 000 XOF` |
| Coût réel initial | `0` _(mis à jour au fil des achats)_ |
| Avancement initial | `0%` |

**5. Adresse du chantier**
- `Villa Bloc B, Bastos, Yaoundé`

**6. Enregistrer** → code `PRJ00002`

---

## Processus 10.2 — Mettre à jour l'avancement d'un projet

### Objectif
Suivre le % de réalisation et les coûts réels au fil du chantier.

### Étapes

**1. Projets → PRJ00002 → Modifier**

**2. Section Avancement**
- Avancement : faire glisser vers `35%` ou saisir `35`

**3. Section Financier**
- Coût réel : `4 800 000 XOF` (achats + main d'œuvre engagés)

**4. Statut** : `En cours` → rester sur En cours

**5. Enregistrer**

### Résultat attendu
- La barre de progression affiche 35%
- Le rapport Rentabilité projets (Rapports → onglet Rentabilité) montre le projet avec budget/coût/marge

---

## Processus 10.3 — Passer un projet en attente (on hold)

### Objectif
Suspendre temporairement un projet (intempéries, problème foncier, client absent).

### Étapes

**1. Projets → PRJ00002 → Modifier**

**2. Statut → `En attente`**

**3. Notes** : `Arrêt chantier décidé le 10/05/2026 — en attente autorisation mairie`

**4. Enregistrer**

---

## Processus 10.4 — Clôturer un projet terminé

### Objectif
Marquer le projet comme terminé après réception des travaux par le client.

### Étapes

**1. Projets → PRJ00002 → Modifier**

**2. Avancement → `100%`**

**3. Statut → `Terminé`**

**4. Date de fin réelle** → _(date de réception)_

**5. Coût réel final** → valeur définitive

**6. Enregistrer**

### Résultat attendu
- Le rapport Rentabilité affiche la marge finale du projet
- Le projet reste consultable mais n'apparaît plus dans les projets actifs

---

## Processus 10.5 — Gérer plusieurs projets simultanément

### Objectif
Avoir une vue d'ensemble de tous les projets actifs, leur statut et leur avancement.

### Étapes

**1. Menu → Projets** → vue liste

**2. Filtres disponibles**
- Statut : `En cours` / `En attente` / `Terminé` / `Clôturé`
- Client
- Chef de projet

**3. Rapports → Rentabilité projets**
- Vue financière consolidée de tous les projets

### Données de test — 3 projets à créer

| Code | Titre | Client | Statut | Avancement | Budget |
|------|-------|--------|--------|------------|--------|
| PRJ00001 | Construction siège BIMEX | BIMEX SARL | En cours | 15% | 48 000 000 |
| PRJ00002 | Rénovation villa Ayissi | M. Ayissi | En cours | 35% | 12 500 000 |
| PRJ00003 | Ravalement façade IMMO TRUST | IMMO TRUST SA | Planifié | 0% | 6 800 000 |
