# 21 — Cycle complet d'un projet de construction / rénovation

> **De la demande client à la clôture financière**  
> **Acteurs impliqués :** Commercial · Chef de projet · Technicien terrain · Comptable · Direction  
> **Durée du scénario de test :** ~90 minutes  
> **Modules utilisés :** Clients · Offres · Devis · Projets · Planning · Suivi terrain · Achats · Facturation · Documents · Rapports · Audit

---

## Scénario

> **BIMEX SARL** (entreprise immobilière, Douala) souhaite construire son nouveau siège social à Akwa.  
> Elle contacte **KREOBAT SARL** le 01/04/2026. Un commercial prend en charge la demande,  
> chiffre les travaux, obtient la signature du marché, pilote le chantier de mai à décembre 2026,  
> facture en 3 situations et clôture le projet après réception par le client.

---

## PHASE 1 — ACQUISITION COMMERCIALE

### Étape 1.1 — Enregistrer le client

**Module :** Clients → Nouveau

| Champ | Valeur |
|-------|--------|
| Nom | `BIMEX SARL` |
| Type | `Entreprise` |
| Email | `contact@bimex.cm` |
| Téléphone | `+237 2 33 42 00 00` |
| Adresse | `BP 2200, Akwa, Douala` |
| Contact principal | `M. Biko Martin, Directeur Général` |
| NINEA | `M052019876A` |

✅ **Enregistrer** → Client `CLI00001` créé

---

### Étape 1.2 — Enregistrer l'offre entrante

**Module :** Offres → Nouveau

| Champ | Valeur |
|-------|--------|
| Client | `BIMEX SARL` |
| Titre | `Construction siège social Akwa — Immeuble R+3` |
| Type | `Projet` |
| Description | `Construction d'un immeuble R+3, surface totale 800m², à Akwa Douala. Comprend gros œuvre, second œuvre, finitions et VRD.` |
| Source | `Appel entrant` |
| Priorité | `Haute` |
| Statut | `Nouveau` |

✅ **Enregistrer** → `OFF00001`

---

### Étape 1.3 — Planifier la visite de reconnaissance

**Module :** Planning → Nouveau rendez-vous

| Champ | Valeur |
|-------|--------|
| Titre | `Visite terrain — Reconnaissance parcelle BIMEX` |
| Type | `Visite terrain` |
| Début | `05/04/2026 à 10h00` |
| Fin | `05/04/2026 à 12h00` |
| Lieu | `Parcelle Akwa, Douala (coordonnées GPS : 4.0383, 9.7085)` |
| Responsable | `Jean Dupont` |
| Client | `BIMEX SARL` |

✅ **Enregistrer** → `APT00001`

Après la visite, mettre à jour le statut → **Réalisé**, notes : `Parcelle 40x20m, accès facile, sol argileux — rapport de sol nécessaire`

---

### Étape 1.4 — Uploader le rapport de sol

**Module :** Documents → Nouveau

| Champ | Valeur |
|-------|--------|
| Titre | `Rapport de sol parcelle Akwa — Cabinet GeoTech` |
| Type | `Rapport` |
| Client | `BIMEX SARL` |
| Fichier | _(PDF rapport géotechnique)_ |

✅ **Uploader**

---

### Étape 1.5 — Créer le devis

**Module :** Devis → Nouveau  
_(ou depuis OFF00001 → Créer un devis)_

**En-tête**

| Champ | Valeur |
|-------|--------|
| Client | `BIMEX SARL` |
| Titre | `DEV — Construction siège social BIMEX, Akwa` |
| Date d'émission | `10/04/2026` |
| Validité | `30 jours` |
| Conditions | `40% commande — 40% mi-travaux — 20% réception` |

**Lignes de devis**

| Description | Qté | Unité | PU HT | Total HT |
|-------------|-----|-------|-------|----------|
| Installation de chantier | 1 | Forfait | 2 500 000 | 2 500 000 |
| Fondations en béton armé | 1 | Forfait | 6 500 000 | 6 500 000 |
| Élévation murs RDC (parpaing 15) | 320 | m² | 35 000 | 11 200 000 |
| Dalle de compression RDC | 180 | m² | 28 000 | 5 040 000 |
| Élévation murs R+1 | 320 | m² | 35 000 | 11 200 000 |
| Dalle R+1 | 180 | m² | 28 000 | 5 040 000 |
| Élévation murs R+2 | 320 | m² | 35 000 | 11 200 000 |
| Dalle R+2 | 180 | m² | 28 000 | 5 040 000 |
| Toiture (charpente + couverture zinc) | 1 | Forfait | 9 500 000 | 9 500 000 |
| Enduit façade (4 faces) | 1 200 | m² | 8 500 | 10 200 000 |
| Carrelage sols RDC + R+1 + R+2 | 540 | m² | 12 500 | 6 750 000 |
| Menuiserie aluminium (portes + fenêtres) | 1 | Forfait | 7 800 000 | 7 800 000 |
| Plomberie sanitaire | 1 | Forfait | 4 200 000 | 4 200 000 |
| Électricité complète | 1 | Forfait | 5 800 000 | 5 800 000 |
| VRD + clôture | 1 | Forfait | 3 500 000 | 3 500 000 |

- **Total HT :** 109 470 000 XOF
- **TVA 19,25% :** 21 072 975 XOF
- **Total TTC :** 130 542 975 XOF

✅ **Enregistrer** → `DEV00001` statut `Brouillon`

---

### Étape 1.6 — Réunion de présentation du devis

**Module :** Planning → Nouveau rendez-vous

| Champ | Valeur |
|-------|--------|
| Titre | `Présentation devis DEV00001 — BIMEX SARL` |
| Type | `Réunion` |
| Début | `14/04/2026 à 15h00` |
| Lieu | `Bureaux KREOBAT, Salle de réunion` |
| Client | `BIMEX SARL` |

✅ **Enregistrer** → `APT00002`

---

### Étape 1.7 — Envoyer le devis

**Module :** Devis → DEV00001 → **Envoyer**

✅ Statut → `Envoyé`, date d'envoi : `14/04/2026`

---

### Étape 1.8 — Acceptation du devis par le client

Le client rappelle le 20/04/2026 pour annoncer son accord.

**Module :** Devis → DEV00001 → **Accepter**

| Champ | Valeur |
|-------|--------|
| Date d'acceptation | `20/04/2026` |

✅ Statut → `Accepté`

> **Entrée audit automatique :** `quotes updated` — status: sent → accepted

---

## PHASE 2 — DÉMARRAGE DU PROJET

### Étape 2.1 — Créer le projet

**Module :** Projets → Nouveau  
_(ou depuis DEV00001 → Créer un projet)_

| Champ | Valeur |
|-------|--------|
| Titre | `Construction siège social BIMEX — Akwa Douala` |
| Client | `BIMEX SARL` _(pré-rempli)_ |
| Devis lié | `DEV00001` |
| Type | `Construction neuve` |
| Numéro de contrat | `CTR-2026-041` |
| Priorité | `Haute` |
| Date de début | `01/05/2026` |
| Date de fin prévue | `31/12/2026` |
| Budget interne | `90 000 000 XOF` _(coût de revient estimé)_ |
| Chef de projet | `Jean Dupont` |
| Adresse chantier | `Parcelle Akwa, Douala` |
| Statut | `En cours` |
| Avancement | `0%` |

✅ **Enregistrer** → `PRJ00001`

---

### Étape 2.2 — Uploader le contrat de marché signé

**Module :** Documents → Nouveau

| Champ | Valeur |
|-------|--------|
| Titre | `Contrat de marché CTR-2026-041 signé` |
| Type | `Contrat` |
| Client | `BIMEX SARL` |
| Projet | `PRJ00001` |
| Fichier | _(PDF contrat signé par les deux parties)_ |

✅ **Uploader**

---

### Étape 2.3 — Facturer l'acompte de démarrage (40%)

**Module :** Facturation → Nouveau

| Champ | Valeur |
|-------|--------|
| Client | `BIMEX SARL` |
| Projet | `PRJ00001` |
| Titre | `Acompte 40% — Marché construction siège BIMEX` |
| Date d'émission | `22/04/2026` |
| Date d'échéance | `30/04/2026` |

**Ligne :**

| Description | Qté | PU HT | Total HT |
|-------------|-----|-------|----------|
| Acompte 40% sur marché CTR-2026-041 (Prix HT 109 470 000 XOF) | 1 | 43 788 000 | 43 788 000 |

- TTC : **52 216 785 XOF**

✅ **Enregistrer** → `FAC00001` → **Envoyer**

---

### Étape 2.4 — Enregistrer le paiement de l'acompte

Le client vire l'acompte le 29/04/2026.

**Module :** Facturation → FAC00001 → **Enregistrer paiement**

| Champ | Valeur |
|-------|--------|
| Montant | `52 216 785 XOF` |
| Mode | `Virement bancaire` |
| Date | `29/04/2026` |
| Référence | `VIR-042-2026` |

✅ Statut FAC00001 → `Payé`

---

### Étape 2.5 — Réunion de démarrage chantier (kick-off)

**Module :** Planning → Nouveau rendez-vous

| Champ | Valeur |
|-------|--------|
| Titre | `Réunion de démarrage chantier PRJ00001` |
| Type | `Réunion` |
| Début | `30/04/2026 à 09h00` |
| Lieu | `Chantier Akwa + Bureaux KREOBAT` |
| Client | `BIMEX SARL` |
| Projet | `PRJ00001` |

✅ **Enregistrer** → `APT00003`

---

## PHASE 3 — EXÉCUTION DU CHANTIER

### Étape 3.1 — Créer un bon de commande matériaux (Fondations)

**Module :** Achats → Nouveau

| Champ | Valeur |
|-------|--------|
| Fournisseur | `Cimencam Douala` |
| Projet | `PRJ00001` |
| Titre | `Matériaux fondations — Mai 2026` |
| Date | `02/05/2026` |

**Lignes :**

| Article | Qté | Unité | PU HT | Total HT |
|---------|-----|-------|-------|----------|
| Ciment Portland | 350 | Sacs | 8 500 | 2 975 000 |
| Fer à béton ø12 | 120 | Barres | 12 000 | 1 440 000 |
| Sable de rivière | 25 | m³ | 35 000 | 875 000 |
| Gravier 15/25 | 20 | m³ | 45 000 | 900 000 |

- **Total TTC :** 7 367 488 XOF

✅ **Enregistrer** → `BC00001` → **Soumettre** → **Approuver** → **Réceptionner**

---

### Étape 3.2 — Rapport journalier semaine 1 (Fondations)

**Module :** Suivi terrain → Nouveau rapport

| Champ | Valeur |
|-------|--------|
| Projet | `PRJ00001` |
| Date | `08/05/2026` |
| Météo | `Ensoleillé` |
| Ouvriers | `12` |
| Avancement | `5%` |
| Travaux réalisés | `Implantation et piquetage terminés. Fouilles fondations débutées (80% réalisé). Ferraillage semelles démarré.` |
| Incidents | `RAS` |
| Prochaines étapes | `Fin ferraillage semelles + coulage béton de propreté semaine prochaine.` |

✅ **Enregistrer** → `RPT00001` → **Soumettre** → **Valider**

---

### Étape 3.3 — Rapport semaine 4 (Fondations terminées)

| Champ | Valeur |
|-------|--------|
| Date | `29/05/2026` |
| Avancement | `12%` |
| Travaux réalisés | `Fondations en béton armé terminées. Élévation murs RDC débutée (20% murs nord et ouest).` |
| Incidents | `Pluie du 26/05 — arrêt 1 journée. Décalage planning de 1 semaine.` |

✅ **Enregistrer** → `RPT00002` → **Soumettre** → **Valider**

> Mettre à jour le projet : avancement → **12%**, statut → `En cours`

---

### Étape 3.4 — Jalon : Fin gros œuvre RDC

**Module :** Planning → Nouveau rendez-vous

| Champ | Valeur |
|-------|--------|
| Titre | `Jalon — Réception gros œuvre RDC par client` |
| Type | `Visite terrain` |
| Début | `15/06/2026 à 10h00` |
| Client | `BIMEX SARL` |
| Projet | `PRJ00001` |

✅ **Enregistrer** → `APT00004` → après visite → statut **Réalisé**

---

### Étape 3.5 — Facturer la situation mi-travaux (40%)

Travaux réalisés représentent environ 40% du marché.

**Module :** Facturation → Nouveau

| Champ | Valeur |
|-------|--------|
| Titre | `Situation N°1 — Travaux juin 2026 (40% marché)` |
| Client | `BIMEX SARL` |
| Projet | `PRJ00001` |
| Date d'émission | `30/06/2026` |
| Date d'échéance | `31/07/2026` |

**Lignes :**

| Description | PU HT |
|-------------|-------|
| Situation N°1 — 40% marché CTR-2026-041 (base HT 109 470 000) | 43 788 000 |

- **TTC :** 52 216 785 XOF

✅ **Enregistrer** → `FAC00002` → **Envoyer**

Le client règle 40 000 000 XOF le 20/07/2026 (partiel).

**Facturation → FAC00002 → Enregistrer paiement**
- Montant : `40 000 000`, Date : `20/07/2026`
- Statut → `Partiellement payé`, Reste dû : `12 216 785 XOF`

---

### Étape 3.6 — Achats second œuvre

**Module :** Achats → Nouveau → `BC00002`

| Article | Fournisseur | Total HT |
|---------|-------------|----------|
| Carrelage 60x60 granit | Céramique Import SARL | 5 130 000 |
| Menuiserie alu (portes+fenêtres) | Alu Pro Douala | 7 800 000 |
| Peinture façade + intérieure | Chantiers Couleurs CM | 1 850 000 |

- **Total TTC :** 17 551 788 XOF

✅ **Approuver** → **Réceptionner** (partiel : menuiserie en attente)

---

### Étape 3.7 — Rapports mensuels (juillet à novembre)

Créer 1 rapport par mois pour suivre l'avancement :

| Rapport | Date | Avancement | Météo | Ouvriers |
|---------|------|------------|-------|----------|
| RPT00003 | 31/07/2026 | 35% | Ensoleillé | 20 |
| RPT00004 | 31/08/2026 | 55% | Pluvieux | 18 |
| RPT00005 | 30/09/2026 | 70% | Nuageux | 22 |
| RPT00006 | 31/10/2026 | 85% | Ensoleillé | 20 |
| RPT00007 | 30/11/2026 | 95% | Ensoleillé | 16 |

Mettre à jour le projet à chaque rapport : avancement + coût réel cumulé.

---

## PHASE 4 — RÉCEPTION DES TRAVAUX

### Étape 4.1 — Planifier la visite de réception finale

**Module :** Planning → Nouveau rendez-vous

| Champ | Valeur |
|-------|--------|
| Titre | `Réception définitive travaux — Siège BIMEX` |
| Type | `Visite terrain` |
| Début | `15/12/2026 à 09h00` |
| Fin | `15/12/2026 à 13h00` |
| Lieu | `Siège BIMEX, Akwa Douala` |
| Client | `BIMEX SARL` |
| Projet | `PRJ00001` |
| Notes | `Présence DG + architecte + chef de chantier. Apporter documents de garantie.` |

✅ **Enregistrer** → `APT00005` → après visite → **Réalisé**

---

### Étape 4.2 — Établir le PV de réception

**Module :** Suivi terrain → Nouveau rapport

| Champ | Valeur |
|-------|--------|
| Projet | `PRJ00001` |
| Date | `15/12/2026` |
| Avancement | `100%` |
| Travaux réalisés | `Travaux de construction du siège social BIMEX entièrement achevés. Nettoyage de chantier terminé. Livraison des clés effectuée.` |
| Incidents | `Réserves mineures : quelques joints de carrelage à reprendre (niveau 3) — délai 15 jours.` |
| **PV signé** | ✅ Oui |
| Date signature | `15/12/2026` |
| Signataire client | `M. Biko Martin, DG BIMEX SARL` |

✅ **Enregistrer** → `RPT00008` → **Soumettre** → **Valider**

---

### Étape 4.3 — Uploader les documents de livraison

**Module :** Documents → Uploader

| Document | Type |
|----------|------|
| PV de réception définitive signé | PV |
| Note de calcul structure | Rapport |
| Attestation conformité électrique | Attestation |
| Plans conformes à l'exécution (DOE) | Plan |
| Carnet d'entretien bâtiment | Rapport |

---

## PHASE 5 — CLÔTURE FINANCIÈRE

### Étape 5.1 — Facturer le solde final (20%)

**Module :** Facturation → Nouveau

| Champ | Valeur |
|-------|--------|
| Titre | `Solde final 20% + régularisation — Marché BIMEX` |
| Client | `BIMEX SARL` |
| Projet | `PRJ00001` |
| Date d'émission | `16/12/2026` |
| Date d'échéance | `31/12/2026` |

**Lignes :**

| Description | Total HT |
|-------------|----------|
| Solde 20% marché CTR-2026-041 | 21 894 000 |
| Solde FAC00002 impayé | 10 204 809 _(conversion HT de 12 216 785 TTC)_ |
| Travaux supplémentaires avenant N°1 | 1 500 000 |

- **Total TTC :** 40 051 737 XOF

✅ **Enregistrer** → `FAC00003` → **Envoyer**

---

### Étape 5.2 — Enregistrer le paiement du solde

Le client règle intégralement le 28/12/2026.

**Facturation → FAC00003 → Enregistrer paiement**
- Montant : `40 051 737 XOF`
- Date : `28/12/2026`
- Référence : `VIR-121-2026`

✅ Statut → `Payé`

---

### Étape 5.3 — Clôturer le projet

**Module :** Projets → PRJ00001 → Modifier

| Champ | Valeur |
|-------|--------|
| Avancement | `100%` |
| Statut | `Terminé` |
| Coût réel final | `87 500 000 XOF` _(somme de tous les BC + main d'œuvre)_ |
| Notes | `Projet livré le 15/12/2026 avec 15 jours de retard (intempéries). Réserves levées le 30/12/2026. Client satisfait.` |

✅ **Enregistrer**

---

## PHASE 6 — ANALYSE POST-PROJET

### Étape 6.1 — Rapport Rentabilité

**Module :** Rapports → onglet Rentabilité projets

| Métrique | Valeur |
|----------|--------|
| Chiffre d'affaires TTC | 144 485 307 XOF _(FAC001 + FAC002 + FAC003)_ |
| CA HT | 121 182 000 XOF |
| Coût réel | 87 500 000 XOF |
| **Marge brute** | **33 682 000 XOF** |
| **Marge %** | **27,8%** ✅ _(objectif > 20%)_ |

---

### Étape 6.2 — Vérification AR Aging

**Rapports → Vieillissement AR** → PRJ00001 ne doit plus apparaître (toutes factures payées)

---

### Étape 6.3 — Consultation journal d'audit

**Module :** Audit → filtrer par entité `projects` et `quotes`

Vérifier la traçabilité complète :
- DEV00001 : draft → sent → accepted
- PRJ00001 : created → updated (avancement x8) → updated (terminé)
- FAC001/002/003 : created → sent → paid

---

## Tableau de bord final du projet

| Élément | Référence | Statut |
|---------|-----------|--------|
| Client | CLI00001 — BIMEX SARL | Actif |
| Offre | OFF00001 | Convertie |
| Devis | DEV00001 | Accepté |
| Projet | PRJ00001 | Terminé ✅ |
| Rendez-vous | APT00001 à APT00005 | Tous réalisés |
| Rapports chantier | RPT00001 à RPT00008 | Tous validés |
| Bons de commande | BC00001, BC00002 | Réceptionnés |
| Factures | FAC00001, FAC00002, FAC00003 | Toutes payées ✅ |
| Documents | 9 documents archivés | — |
| Marge projet | 27,8% | ✅ Objectif atteint |
| Délai | +15 jours (intempéries) | Acceptable |
