# 11 — Suivi terrain (Rapports de chantier)

> **Acteur :** 🟡 Chef de projet / 🟢 Technicien terrain  
> **Permission requise :** `suivi.view`, `suivi.create`, `suivi.edit`, `suivi.validate`  
> **Route :** Menu → Suivi terrain (icône clipboard)

---

## Processus 11.1 — Créer un rapport journalier de chantier

### Objectif
Rédiger le compte-rendu quotidien des travaux réalisés sur un chantier actif.

### Prérequis
- Projet PRJ00001 ou PRJ00002 existant et en cours
- Permission `suivi.create`

### Étapes

**1. Menu → Suivi terrain → Nouveau rapport**

**2. Section Informations générales**

| Champ | Valeur de test |
|-------|---------------|
| Projet | `PRJ00001 — Construction siège BIMEX` |
| Client | _(auto-rempli : BIMEX SARL)_ |
| Responsable | `Jean Dupont` |
| Date du rapport | `15/05/2026` |

**3. Section Conditions & Avancement**

| Champ | Valeur |
|-------|--------|
| Météo | `Ensoleillé` |
| Nombre d'ouvriers présents | `18` |
| Avancement global | `22%` |

**4. Section Compte-rendu**

| Champ | Valeur de test |
|-------|---------------|
| Travaux réalisés | `Coulage dalle R+1 (180m²). Ferraillage plancher haut RDC terminé. Pose parpaings murs R+1 nord et est (80ml).` |
| Incidents / observations | `Livraison fer retardée de 2h. Bétonnière en panne remplacée par location.` |
| Prochaines étapes | `Décoffrage dalle R+1 prévu J+7. Démarrage murs R+2 semaine prochaine.` |
| Notes internes | `Prestataire Acier Plus cm à relancer pour livraison fers R+2.` |

**5. Enregistrer** → code `RPT00001`, statut `Brouillon`

### Résultat attendu
- Rapport créé, visible dans la liste avec statut Brouillon
- Lié au projet PRJ00001 et au client BIMEX SARL

---

## Processus 11.2 — Soumettre un rapport pour validation

### Objectif
Transmettre le rapport au responsable pour approbation.

### Étapes

**1. Suivi terrain → RPT00001 → Ouvrir**

**2. Relire le rapport** et corriger si nécessaire (statut Brouillon = modifiable)

**3. Bouton Soumettre** (barre d'actions en haut)

### Résultat attendu
- Statut passe à `Soumis`
- Le rapport n'est plus modifiable par le rédacteur
- Le responsable peut maintenant le valider

---

## Processus 11.3 — Valider un rapport (Responsable)

### Objectif
Approuver officiellement le rapport journalier soumis par le technicien.

### Prérequis
- Permission `suivi.validate`

### Étapes

**1. Suivi terrain → liste → filtrer par statut `Soumis`**

**2. Cliquer sur RPT00001**

**3. Bouton Valider**

### Résultat attendu
- Statut passe à `Validé`
- Le rapport est verrouillé définitivement
- Il entre dans l'historique officiel du chantier

---

## Processus 11.4 — Attacher des photos au rapport

### Objectif
Documenter visuellement l'avancement du chantier.

### Prérequis
- Rapport RPT00001 en statut Brouillon ou Soumis (pas encore Validé)

### Étapes

**1. Suivi terrain → RPT00001 → Modifier**

**2. Section Documents & Photos** (en bas du formulaire)

**3. Cliquer sur Ajouter un document**
- Sélectionner une image (JPG, PNG) ou PDF
- Titre : `Photo avancement dalle R+1 — 15/05/2026`
- ✅ Upload

**4. Répéter** pour autant de photos que nécessaire

### Résultat attendu
- Les fichiers apparaissent dans le panneau Documents du rapport
- Ils sont également accessibles depuis la bibliothèque DMS

---

## Processus 11.5 — Établir un PV de réception client

### Objectif
Faire signer au client la réception des travaux lors de la visite de fin de chantier.

### Prérequis
- Travaux terminés, client présent ou représentant délégué

### Étapes

**1. Suivi terrain → RPT00001 → Modifier** (ou nouveau rapport dédié à la réception)

**2. Section PV de réception**

| Champ | Valeur |
|-------|--------|
| PV signé | ✅ cocher |
| Date de signature | `30/08/2026` |
| Nom du signataire client | `M. Biko Martin, DG BIMEX SARL` |

**3. Enregistrer puis Soumettre puis Valider**

### Résultat attendu
- Le rapport affiche le badge **PV signé** dans la liste
- La date de signature est enregistrée dans l'historique
- Le projet peut être passé en statut `Terminé`

---

## Processus 11.6 — Consulter l'historique des rapports d'un chantier

### Étapes

**1. Suivi terrain → filtre Projet → `PRJ00001`**

**2. Trier par date** → voir la chronologie complète du chantier

**3. Filtres complémentaires** : par statut (Brouillon / Soumis / Validé)

### Données de test — 3 rapports à créer

| Code | Projet | Date | Météo | Ouvriers | Avancement |
|------|--------|------|-------|----------|------------|
| RPT00001 | PRJ00001 | 15/05/2026 | Ensoleillé | 18 | 22% |
| RPT00002 | PRJ00001 | 16/05/2026 | Nuageux | 16 | 25% |
| RPT00003 | PRJ00002 | 15/05/2026 | Pluvieux | 8 | 40% |
