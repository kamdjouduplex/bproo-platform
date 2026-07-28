# 02 — Analytique & Supervision plateforme

> **Acteur :** 🔴 Administrateur plateforme  
> **Module :** Admin → Analytique (`/admin/analytics`)

---

## Processus 2.1 — Consulter le tableau de bord analytique

### Objectif
Avoir une vue d'ensemble de la santé de la plateforme : nombre de tenants, adoption des modules, répartition des abonnements.

### Étapes

**1. Menu sidebar → Analytique** (icône graphique)

**2. Lire les KPI cards**
- Total entreprises sur la plateforme
- Entreprises actives vs inactives
- Entreprises provisionnées avec succès
- Échecs de provisionnement (si > 0 → action requise)

**3. Bloc Répartition abonnements**
- Barres horizontales : Gratuit / Starter / Pro / Enterprise
- Identifier si la majorité des clients est encore sur plan Gratuit → action commerciale

**4. Bloc Modules les plus adoptés**
- Top 8 des modules activés par le plus grand nombre de tenants
- Les modules peu adoptés peuvent nécessiter une révision de l'offre

**5. Bloc bar chart mensuel**
- Courbe de création de nouveaux tenants sur les 6 derniers mois
- Identifier les pics d'acquisition et les creux

**6. Tableau Dernières entreprises**
- Les 6 entreprises les plus récemment créées
- Vérifier leur statut de provisionnement et leur plan

### Résultat attendu
- Toutes les métriques sont cohérentes avec la réalité des tenants
- Aucun tenant en statut **Échec** non traité

---

## Processus 2.2 — Identifier les tenants à risque

### Objectif
Repérer les tenants dont le plan est expiré ou dont la période d'essai se termine.

### Étapes

**1. Menu sidebar → Entreprises**

**2. Repérer** dans la colonne Plan les badges avec mention **expiré** (texte rouge)

**3. Contacter** l'entreprise concernée pour renouvellement

**4. Mettre à jour** via le bouton Modifier → Section Abonnement → nouvelle date d'expiration

### Résultat attendu
- Aucun tenant actif avec plan expiré sans action en cours
