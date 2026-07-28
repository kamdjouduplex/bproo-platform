# 18 — Gestion documentaire (DMS)

> **Acteur :** 🟢 Tous utilisateurs  
> **Permission requise :** `dms.view`, `dms.create`, `dms.delete`  
> **Route :** Menu → Documents (icône dossier)

---

## Processus 18.1 — Uploader un document dans la bibliothèque centrale

### Objectif
Archiver un document (plan, contrat, attestation, photo) dans la GED de l'entreprise.

### Étapes

**1. Menu → Documents → Nouveau document**

**2. Remplir**

| Champ | Valeur de test |
|-------|---------------|
| Titre | `Plan architectural RDC — Siège BIMEX` |
| Fichier | _(sélectionner un PDF ou image)_ |
| Type | `Plan` |
| Client associé | `BIMEX SARL` |
| Projet associé | `PRJ00001` |
| Notes | `Plans approuvés le 01/04/2026, version 2.1` |

**3. Uploader**

### Résultat attendu
- Le document apparaît dans la bibliothèque DMS
- Il est accessible depuis la fiche client BIMEX SARL et le projet PRJ00001

---

## Processus 18.2 — Attacher un document depuis une entité

### Objectif
Joindre un document directement depuis une fiche projet, devis, rapport ou intervention (sans passer par le DMS central).

### Exemple : Attacher un PV depuis un rapport de suivi terrain

**1. Suivi terrain → RPT00001 → Modifier**

**2. Section Documents & Photos → Ajouter**

**3. Sélectionner le fichier** (PV signé scanné, PDF)

**4. Titre** : `PV réception fondations — BIMEX 15/05/2026`

**5. Enregistrer**

### Résultat attendu
- Le document est accessible depuis le rapport RPT00001
- Il est également indexé dans la bibliothèque DMS centrale

---

## Processus 18.3 — Rechercher un document

### Étapes

**1. Menu → Documents**

**2. Barre de recherche** : saisir `BIMEX` ou `Plan` ou `PV`

**3. Filtres disponibles**
- Par type (Plan / Contrat / Photo / Facture / Autre)
- Par client
- Par projet
- Par date d'upload

---

## Processus 18.4 — Supprimer un document

### Prérequis
- Permission `dms.delete`

### Étapes

**1. Documents → document cible → Supprimer**

**2. Confirmer** la suppression

> ⚠️ La suppression est définitive. Vérifier qu'aucune référence importante n'est perdue.

---

## Types de documents courants dans le BTP

| Type | Exemples |
|------|----------|
| Plans | Plans architecturaux, plans de structure, plans MEP |
| Contrats | Contrat de marché, contrat de maintenance, sous-traitance |
| Attestations | Attestation assurance, agrément, caution bancaire |
| PV | PV de réception, PV de chantier, PV de réunion |
| Photos | Photos chantier, photos avant/après, photos incidents |
| Factures fournisseurs | Factures matériaux, location engins |
| Devis fournisseurs | Offres sous-traitants |
| Rapports | Rapports d'expertise, rapports de sol, diagnostics |

---

## Données de test — 5 documents à uploader

| Titre | Type | Client | Projet |
|-------|------|--------|--------|
| Plan architectural RDC siège BIMEX | Plan | BIMEX SARL | PRJ00001 |
| Contrat de marché CTR-2026-041 | Contrat | BIMEX SARL | PRJ00001 |
| PV réception fondations | PV | BIMEX SARL | PRJ00001 |
| Contrat maintenance IMMO TRUST 2026 | Contrat | IMMO TRUST SA | — |
| Photo avant ravalement façade IMMO | Photo | IMMO TRUST SA | PRJ00003 |
