# 07 — Offres commerciales

> **Acteur :** 🟡 Commercial / 🟠 Admin tenant  
> **Permission requise :** `offres.view`, `offres.create`, `offres.edit`  
> **Route :** Menu → Offres

---

## Qu'est-ce qu'une offre ?
Une **offre** est la première étape du cycle commercial. Elle représente une **demande de travaux reçue d'un client**, avant même d'avoir chiffré le montant. Elle permet de qualifier la demande (Projet de construction ? Contrat de maintenance ?) avant d'établir un devis.

---

## Processus 7.1 — Enregistrer une demande client (offre entrante)

### Objectif
Capturer une demande verbale ou écrite d'un client et l'enregistrer pour suivi.

### Prérequis
- Le client BIMEX SARL existe dans le système

### Étapes

**1. Menu → Offres → Nouveau**

**2. Remplir le formulaire**

| Champ | Valeur de test |
|-------|---------------|
| Client | `BIMEX SARL` |
| Titre | `Construction siège social Akwa` |
| Type | `Projet` |
| Description | `Construction d'un immeuble R+3, 800m², à Akwa Douala. Gros œuvre + second œuvre` |
| Source | `Appel entrant` |
| Priorité | `Haute` |

**3. Enregistrer**
- Code auto-généré : ex. `OFF00001`

### Résultat attendu
- L'offre apparaît dans la liste Kanban ou tableau avec statut `Nouveau`
- Elle est liée au client BIMEX SARL et visible dans son historique

---

## Processus 7.2 — Qualifier et faire avancer une offre

### Objectif
Faire progresser une offre de **Nouveau** → **En étude** → **Devis en cours** selon l'avancement commercial.

### Étapes

**1. Menu → Offres → Kanban**

**2. Glisser la carte OFF00001** de la colonne **Nouveau** vers **En étude**

**3. Ou via tableau** : ouvrir l'offre → modifier le statut

**4. Quand le chiffrage démarre** : passer en **Devis en cours**

---

## Processus 7.3 — Convertir une offre en devis

### Objectif
Initier la rédaction du devis directement depuis l'offre qualifiée.

### Étapes

**1. Offres → OFF00001 → Voir**

**2. Bouton Créer un devis** (ou bouton de conversion)

**3. Le formulaire devis s'ouvre** pré-rempli avec le client et le titre de l'offre

**4. Compléter les lignes du devis** (voir manuel 08)

### Résultat attendu
- Un devis est créé et lié à l'offre OFF00001
- L'offre passe automatiquement en statut **Devis émis**

---

## Processus 7.4 — Clôturer une offre perdue

### Objectif
Marquer une offre sans suite pour ne pas la laisser polluer le pipeline.

### Étapes

**1. Offres → ligne de l'offre → Modifier**

**2. Statut → `Perdu`**

**3. Note de clôture** : `Client a choisi un autre prestataire (moins-disant)`

**4. Enregistrer**
