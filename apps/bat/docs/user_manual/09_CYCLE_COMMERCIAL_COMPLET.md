# 09 — Cycle complet : Offre → Devis → Projet → Facture

> **Acteur :** 🟡 Commercial + Chef de projet + Comptable  
> **Durée du scénario :** ~45 minutes de test  
> **Objectif :** Tester le flux de bout en bout sans interruption

---

## Scénario

> BIMEX SARL contacte KREOBAT pour la construction de son siège social à Akwa, Douala.  
> Le commercial reçoit la demande, établit un devis, le client l'accepte,  
> un projet est lancé, les travaux démarrent, une facture d'acompte est émise et réglée.

---

## Étape 1 — Créer le client (si pas encore fait)

- Menu → Clients → Nouveau
- Nom : `BIMEX SARL`, Type : Entreprise, Douala
- ✅ Sauvegarder

---

## Étape 2 — Enregistrer l'offre

- Menu → Offres → Nouveau
- Client : `BIMEX SARL`
- Titre : `Construction siège social Akwa`
- Type : `Projet`
- Statut : `Nouveau`
- ✅ Sauvegarder → code `OFF00001`

---

## Étape 3 — Créer le devis lié

- Offres → OFF00001 → Créer un devis
- Ajouter 3 lignes minimum (voir manuel 08 pour données)
- Total TTC indicatif : **58 000 000 XOF**
- ✅ Sauvegarder → `DEV00001` statut Brouillon

---

## Étape 4 — Envoyer le devis

- Devis → DEV00001 → **Envoyer**
- Statut → `Envoyé`

---

## Étape 5 — Accepter le devis (côté client)

- Devis → DEV00001 → **Accepter**
- Date acceptation : _(aujourd'hui)_
- Statut → `Accepté`

---

## Étape 6 — Créer le projet

- Devis → DEV00001 → **Créer un projet**
- Ou Menu → Projets → Nouveau

| Champ | Valeur |
|-------|--------|
| Titre | `Construction siège social BIMEX` |
| Client | `BIMEX SARL` _(pré-rempli)_ |
| Devis lié | `DEV00001` _(pré-rempli)_ |
| Statut | `En cours` |
| Date début | `15/04/2026` |
| Date fin prévue | `31/12/2026` |
| Budget | `48 000 000` _(coût interne)_ |
| Chef de projet | `Admin Kreobat` |

- ✅ Sauvegarder → code `PRJ00001`

---

## Étape 7 — Émettre la facture d'acompte (40%)

- Menu → Facturation → Nouveau

| Champ | Valeur |
|-------|--------|
| Client | `BIMEX SARL` |
| Projet | `PRJ00001` |
| Titre | `Acompte 40% — Construction siège BIMEX` |
| Ligne 1 | Acompte 40% sur marché, 1 unité, `23 200 000 XOF` HT |

- ✅ Sauvegarder → `FAC00001` statut Brouillon
- → **Envoyer** → statut `Envoyé`

---

## Étape 8 — Enregistrer le paiement de l'acompte

- Facturation → FAC00001 → **Enregistrer paiement**
- Montant : `27 592 000 XOF` (TTC)
- Mode : `Virement bancaire`
- Date : _(aujourd'hui)_
- ✅ Statut → `Payé`

---

## Étape 9 — Créer un rendez-vous de démarrage chantier

- Menu → Planning → Nouveau rendez-vous
- Titre : `Réunion démarrage chantier BIMEX`
- Type : `Réunion`
- Date : `20/04/2026 à 09h00`
- Client : `BIMEX SARL`, Projet : `PRJ00001`
- ✅ Sauvegarder → `APT00001`

---

## Résultat attendu — Vérifications finales

| Point de contrôle | Attendu |
|-------------------|---------|
| OFF00001 statut | `Devis émis` |
| DEV00001 statut | `Accepté` |
| PRJ00001 statut | `En cours` |
| FAC00001 statut | `Payé` |
| Client BIMEX SARL — Historique | Offre + Devis + Projet + Facture visibles |
| Rapport Revenus | 27 592 000 XOF encaissé ce mois |
| Rapport Rentabilité | PRJ00001 visible avec budget vs coût |
| Planning | APT00001 visible le 20/04/2026 |
