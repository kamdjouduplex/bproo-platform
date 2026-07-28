# 06 — Gestion des clients

> **Acteur :** 🟡 Commercial / 🟠 Admin tenant  
> **Permission requise :** `clients.view`, `clients.create`, `clients.edit`  
> **Route :** Menu → Clients

---

## Processus 6.1 — Créer une fiche client

### Objectif
Enregistrer un nouveau client (particulier ou entreprise) qui sera la pièce centrale de toutes les offres, devis, projets et factures.

### Étapes

**1. Menu → Clients → Nouveau**

**2. Remplir la fiche**

| Champ | Client entreprise | Client particulier |
|-------|------------------|-------------------|
| Nom | `BIMEX SARL` | `M. Ayissi Jean-Paul` |
| Type | `Entreprise` | `Particulier` |
| Email | `contact@bimex.cm` | `j.ayissi@gmail.com` |
| Téléphone | `+237 2 33 42 00 00` | `+237 6 77 88 99 00` |
| Adresse | `BP 2200, Akwa, Douala` | `Bastos, Yaoundé` |
| NINEA / Registre commerce | `M052019876A` | — |
| Contact principal | `M. Biko Martin, DG` | — |

**3. Enregistrer**

### Résultat attendu
- La fiche client apparaît dans la liste
- Le client est maintenant disponible dans tous les sélecteurs (devis, projets, maintenance, planning)
- L'historique du client est vierge et se remplira au fil des transactions

---

## Processus 6.2 — Consulter l'historique d'un client

### Objectif
Avoir une vue complète de tout ce qui a été fait avec un client donné.

### Étapes

**1. Menu → Clients → ligne BIMEX SARL → Voir**

**2. Onglets disponibles**
- **Offres** : demandes reçues de ce client
- **Devis** : tous les devis émis
- **Projets** : projets en cours et terminés
- **Factures** : historique de facturation et impayés
- **Maintenance** : contrats et ordres de service
- **Documents** : pièces jointes associées

### Résultat attendu
- Vision 360° du client sans avoir à naviguer module par module

---

## Processus 6.3 — Modifier une fiche client

### Objectif
Mettre à jour les coordonnées d'un client existant.

### Étapes

**1. Clients → ligne client → Modifier**

**2. Modifier les champs souhaités** (téléphone, adresse, contact…)

**3. Enregistrer**

### Résultat attendu
- Les nouvelles coordonnées apparaissent sur les documents générés après la modification
- L'historique reste intact

---

## Processus 6.4 — Rechercher un client

### Objectif
Retrouver rapidement un client parmi une longue liste.

### Étapes

**1. Menu → Clients**

**2. Barre de recherche** → saisir nom, email ou téléphone (ex. `BIMEX`)

**3. La liste se filtre en temps réel**

### Données de test — 3 clients à créer

| Nom | Type | Ville |
|-----|------|-------|
| BIMEX SARL | Entreprise | Douala |
| IMMO TRUST SA | Entreprise | Yaoundé |
| M. Ayissi Jean-Paul | Particulier | Yaoundé |
