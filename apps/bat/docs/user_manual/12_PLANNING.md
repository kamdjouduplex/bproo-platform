# 12 — Planning & Calendrier

> **Acteur :** 🟡 Chef de projet / 🟢 Commercial / 🟢 Technicien  
> **Permission requise :** `planning.view`, `planning.create`, `planning.edit`, `planning.delete`  
> **Route :** Menu → Planning (icône calendrier)

---

## Processus 12.1 — Planifier un rendez-vous client

### Objectif
Enregistrer une visite, réunion ou jalon dans le calendrier partagé de l'entreprise.

### Étapes

**1. Menu → Planning → Nouveau** (ou cliquer sur une date dans le calendrier)

**2. Remplir le formulaire**

| Champ | Valeur de test |
|-------|---------------|
| Titre | `Visite chantier BIMEX — Réception fondations` |
| Type | `Visite terrain` |
| Statut | `Planifié` |
| Début | `20/05/2026 à 09h00` |
| Fin | `20/05/2026 à 11h00` |
| Lieu | `Chantier Akwa, Douala` |
| Responsable | `Jean Dupont` _(pré-rempli avec l'utilisateur connecté)_ |
| Client | `BIMEX SARL` |
| Projet | `PRJ00001 — Construction siège BIMEX` |
| Notes | `Présence du DG et du chef de chantier. Apporter plans révisés.` |

**3. Enregistrer** → code `APT00001`

### Résultat attendu
- La pill colorée **Visite terrain** apparaît sur le 20 mai dans le calendrier mensuel
- Le rendez-vous est lié au client BIMEX SARL et au projet PRJ00001

---

## Processus 12.2 — Planifier une réunion interne

| Champ | Valeur |
|-------|--------|
| Titre | `Réunion hebdo équipe chantier` |
| Type | `Réunion` |
| Début | `19/05/2026 à 08h00` |
| Fin | `19/05/2026 à 09h00` |
| Lieu | `Bureau KREOBAT, Salle de réunion` |
| Responsable | `Admin Kreobat` |
| Client | _(laisser vide)_ |

---

## Processus 12.3 — Planifier une maintenance préventive

| Champ | Valeur |
|-------|--------|
| Titre | `Visite préventive trimestrielle — IMMO TRUST` |
| Type | `Maintenance` |
| Début | `01/06/2026 à 10h00` |
| Fin | `01/06/2026 à 14h00` |
| Client | `IMMO TRUST SA` |
| Ordre de maintenance | _(lier à un ordre existant si disponible)_ |

---

## Processus 12.4 — Planifier un jalon projet

| Champ | Valeur |
|-------|--------|
| Titre | `Jalon — Réception gros œuvre PRJ00001` |
| Type | `Jalon projet` |
| Début | `30/06/2026 à 00h00` |
| Fin | `30/06/2026 à 00h00` _(même heure = date symbolique)_ |
| Projet | `PRJ00001` |

---

## Processus 12.5 — Naviguer dans le calendrier

### Vue mensuelle
- **Menu → Planning** → vue calendrier par défaut
- Flèches **◀ ▶** : mois précédent / suivant
- Bouton **Aujourd'hui** : revenir au mois courant
- Pills colorées par type :
  - 🔵 Visite terrain
  - 🟣 Réunion
  - 🟠 Maintenance
  - 🟤 Jalon projet
- Si plus de 3 RDV dans une journée → `+N autres` cliquable

### Filtres
- **Type** : filtrer par catégorie de rendez-vous
- **Responsable** : voir uniquement les RDV d'un collaborateur

### Vue liste
- Bouton **Liste** (en haut à droite du calendrier)
- Affiche les 30 prochains RDV groupés par date
- Colonnes : heure, titre, lieu, responsable, client

---

## Processus 12.6 — Confirmer puis clôturer un rendez-vous

**1. Planning → APT00001 → Modifier**

**2. Statut → `Confirmé`** (envoi confirmation au client)

**3. Après la visite → Statut → `Réalisé`**

**4. Notes** : `Fondations validées. Client satisfait. Passage à l'élévation RDC autorisé.`

**5. Enregistrer**

---

## Processus 12.7 — Annuler un rendez-vous

**1. Planning → APT00001 → Modifier**

**2. Statut → `Annulé`**

**3. Notes** : motif d'annulation

**4. Enregistrer** _(le RDV reste visible pour historique mais n'est plus affiché dans les futurs)_

---

## Données de test — 4 rendez-vous à créer

| Code | Type | Titre | Date | Responsable |
|------|------|-------|------|-------------|
| APT00001 | Visite terrain | Réception fondations BIMEX | 20/05/2026 09h | Jean Dupont |
| APT00002 | Réunion | Réunion hebdo équipe | 19/05/2026 08h | Admin Kreobat |
| APT00003 | Maintenance | Visite préventive IMMO TRUST | 01/06/2026 10h | Pierre Martin |
| APT00004 | Jalon projet | Réception gros œuvre PRJ00001 | 30/06/2026 | Jean Dupont |
