# Manuel utilisateur — Inov-Com ERP

Guide complet pour utiliser la plateforme à 100 % : administration système, gestion des vendeurs, modules métier et bonnes pratiques.

**Version :** basée sur la branche courante (`trams-negoce`)  
**Public :** administrateurs plateforme, responsables boutique, caissiers, comptables, acheteurs, RH

---

## Table des matières

1. [Présentation du système](#1-présentation-du-système)
2. [Connexion et accès](#2-connexion-et-accès)
3. [Administration plateforme (Super Admin)](#3-administration-plateforme-super-admin)
4. [Premiers pas côté vendeur](#4-premiers-pas-côté-vendeur)
5. [Navigation et tableau de bord](#5-navigation-et-tableau-de-bord)
6. [Configuration de la boutique](#6-configuration-de-la-boutique)
7. [Utilisateurs, rôles et permissions](#7-utilisateurs-rôles-et-permissions)
8. [Catalogue — Articles](#8-catalogue--articles)
9. [Clients](#9-clients)
10. [Ventes (Point de vente)](#10-ventes-point-de-vente)
11. [Devis et réservations](#11-devis-et-réservations)
12. [Facturation et livraisons](#12-facturation-et-livraisons)
13. [Paiements factures et relances](#13-paiements-factures-et-relances)
14. [Retours, avoirs et remboursements](#14-retours-avoirs-et-remboursements)
15. [Dettes clients](#15-dettes-clients)
16. [Fournisseurs et achats](#16-fournisseurs-et-achats)
17. [Stock, inventaire et pertes](#17-stock-inventaire-et-pertes)
18. [Caisse](#18-caisse)
19. [Dépenses et rapports](#19-dépenses-et-rapports)
20. [Pharmacie (lots et ordonnances)](#20-pharmacie-lots-et-ordonnances)
21. [Paie et présence (RH)](#21-paie-et-présence-rh)
22. [Tickets et abonnement vendeur](#22-tickets-et-abonnement-vendeur)
23. [Multi-magasin](#23-multi-magasin)
24. [Impressions et exports](#24-impressions-et-exports)
25. [Ordre d’activation des modules](#25-ordre-dactivation-des-modules)
26. [Dépannage utilisateur](#26-dépannage-utilisateur)

---

## 1. Présentation du système

Inov-Com est une **plateforme ERP multi-vendeurs** :

| Niveau | Rôle | Description |
|--------|------|-------------|
| **Plateforme** | Super Admin | Gère les vendeurs (tenants), les abonnements, l’activation des modules |
| **Vendeur** | Utilisateur boutique | Utilise les modules activés pour son activité (ventes, stock, factures…) |

Chaque vendeur possède :

- Un **code** unique (ex. `demo`, `itc`)
- Une **base de données isolée** (données clients, ventes, stock propres)
- Des **modules activables** individuellement (Articles, Ventes, Facturation…)

### Modules disponibles

| Module | Menu | Usage principal |
|--------|------|-----------------|
| Utilisateurs | Système | Comptes, rôles, permissions |
| Configuration | Système | Logo, paramètres, boutiques |
| Articles | Catalogue | Produits, prix, codes-barres |
| Clients | Ventes | Fichier clients, crédit |
| Ventes | Ventes | Encaissement POS |
| Devis | Ventes | Propositions commerciales |
| Réservations | Ventes | Blocage stock pour client |
| Facturation | Ventes | Factures, bons de livraison |
| Paiements factures | Ventes | Encaissements sur factures |
| Retours & Avoirs | Ventes | Retours, crédits, remboursements |
| Dettes | Ventes | Créances et échéanciers |
| Fournisseurs | Achats & Stock | Fichier fournisseurs |
| Achats | Achats & Stock | Commandes et réceptions |
| Stock | Achats & Stock | Niveaux, ajustements, transferts |
| Inventaire | Achats & Stock | Comptages physiques |
| Pertes | Achats & Stock | Casse, expiration, vol |
| Dépenses | Finances | Charges et approbations |
| Caisse | Finances | Sessions caisse, clôtures |
| Rapports | Finances | Tableaux de bord analytiques |
| Paie | RH | Bulletins, employés |
| Présence | RH | Pointage |
| Tickets | Système | Incidents internes |
| Lots / Péremption | Pharmacie | Traçabilité lots |
| Ordonnances | Pharmacie | Dispensation |

Les modules **Utilisateurs** et **Configuration** sont toujours disponibles. Les autres doivent être **installés** par le Super Admin (Packages) ou activés à la création du vendeur.

---

## 2. Connexion et accès

### 2.1 Super Admin (plateforme)

| Élément | Valeur |
|---------|--------|
| URL | `https://votre-domaine.com/admin/login` |
| Exemple prod | `https://erp.afroinov.com/admin/login` |
| Compte initial (install) | `admin@demo.invo` / `password` — **à changer immédiatement** |

Après connexion : tableau de bord admin, menu **Vendeurs**, **Packages**, **Plans**, etc.

### 2.2 Utilisateur vendeur (boutique)

| Élément | Valeur |
|---------|--------|
| URL | `https://votre-domaine.com/app/login?tenant=CODE` |
| Exemple | `https://erp.afroinov.com/app/login?tenant=demo` |

Le paramètre **`tenant=`** est obligatoire. Il correspond au **code vendeur** défini par le Super Admin.

Le compte admin du vendeur est créé lors de **Créer vendeur** (nom, e-mail, mot de passe admin). Ce n’est **pas** le compte Super Admin.

### 2.3 Abonnement expiré ou vendeur inactif

Si le vendeur n’a pas d’abonnement actif, l’utilisateur est redirigé vers :

`/app/subscription?tenant=CODE`

Seules les pages **Connexion**, **Déconnexion** et **Abonnement** restent accessibles jusqu’au renouvellement (Super Admin ou solde).

### 2.4 Interface caissier

Un utilisateur avec le rôle **Caissier** (`cashier`) sans rôle Admin voit une interface **simplifiée** :

- Barre horizontale (pas de menu latéral complet)
- Accès rapide **Nouvelle vente** si le module Ventes est activé

---

## 3. Administration plateforme (Super Admin)

Menu principal : **Admin · Vendeurs · Plans · Packages · Modules · Santé · Events**

### 3.1 Tableau de bord (`/admin`)

- Nombre de vendeurs (total, actifs, en provisionnement, en échec)
- Nombre de modules enregistrés
- Derniers événements d’installation de modules

### 3.2 Gérer les vendeurs (`/admin/tenants`)

#### Créer un vendeur (`/admin/tenants/create`)

1. **Nom du vendeur** — nom affiché (ex. « Boutique Centrale »)
2. **Code vendeur** — identifiant court, sans espace (ex. `demo`, `itc`)
3. **Type d’activité** — retail, pharmacie, boulangerie, restaurant, autre
4. **Contact clé** — nom, téléphone, adresse (optionnel)
5. **Compte admin boutique** — nom, e-mail, mot de passe (premier utilisateur du vendeur)
6. **Multi-magasin** — cocher si plusieurs points de vente

La base PostgreSQL est créée **automatiquement** (`erp_{code}_xxxx`). Aucun champ DB à remplir.

7. Cliquer **Enregistrer**
8. Ouvrir **Santé vendeurs** (`/admin/tenants/health`) — attendre le statut **OK** (1–2 min)
9. En cas d’échec : bouton **Relancer** sur la même page

#### Modifier un vendeur (`/admin/tenants/{code}/edit`)

Mettre à jour nom, contact, activation, type. La base de données n’est pas recréée.

#### Paramètres vendeur (`/admin/tenants/{code}/settings`)

- Devise, locale, fuseau horaire
- Taux de TVA par défaut
- Préfixe factures
- Activation **multi-magasin** (lance la configuration des boutiques)

#### Abonnement vendeur (`/admin/tenants/{code}/subscription`)

- Enregistrer un **paiement** (montant, date, mode)
- Appliquer le **solde** au renouvellement
- Changer de **plan** (mensuel, démo, etc.)
- Consulter l’historique des paiements

#### Santé vendeurs (`/admin/tenants/health`)

- Test de connexion à la base du vendeur
- Statut de provisionnement (En cours / OK / Erreur)
- **Relancer** le provisionnement sans commande serveur

#### Supprimer un vendeur

Depuis la liste : bouton **Supprimer**.  
Supprime l’enregistrement plateforme et les liens modules. **La base PostgreSQL n’est pas supprimée automatiquement** — voir guide admin serveur si nettoyage complet requis.

### 3.3 Packages — Installation des modules (`/admin/packages`)

1. Sélectionner le **vendeur** dans la liste déroulante
2. Parcourir le catalogue (recherche possible)
3. **Installer** — active le module pour ce vendeur (migrations + données initiales)
4. **Désinstaller** — désactive le module (données conservées en base)
5. **Pour tous** — installe le module pour **tous** les vendeurs existants
6. **Synchroniser les modules** — met à jour le catalogue depuis la configuration
7. **Débloquer** — efface un état « En cours… » bloqué après erreur

Statuts affichés : **Non installé** · **Installé** · **Actif (core)**

> **Important :** un seul module par **famille** peut être actif (ex. une seule variante « Ventes »).

### 3.4 Modules par vendeur (`/admin/tenants/modules`)

Alternative à Packages : bascule ON/OFF par module pour un vendeur sélectionné. Même effet qu’installer/désinstaller.

### 3.5 Plans d’abonnement (`/admin/plans`)

- Créer des offres (nom, prix FCFA, intervalle mensuel/annuel)
- Marquer un plan **Démo** (jamais suspendu automatiquement)
- Ordre d’affichage (`sort_order`)

### 3.6 Registre modules (`/admin/modules`)

Catalogue technique (clé, libellé, description). Réservé à la maintenance ; l’activation se fait via **Packages**.

### 3.7 Événements modules (`/admin/module-events`)

Historique des installations/désinstallations par vendeur. **Export** disponible.

---

## 4. Premiers pas côté vendeur

### Checklist mise en service

| Étape | Où | Action |
|-------|-----|--------|
| 1 | Admin → Packages | Installer **Articles**, **Stock**, **Clients**, **Ventes**, **Caisse** |
| 2 | `/app/configuration` | Logo, nom magasin, coordonnées, en-tête factures |
| 3 | `/app/users` | Créer les comptes caissiers / comptables |
| 4 | `/app/roles` | Vérifier les rôles et permissions |
| 5 | `/app/items` | Saisir ou importer le catalogue |
| 6 | `/app/stock` | Vérifier les niveaux initiaux (ou réception achat) |
| 7 | `/app/sales/create` | Première vente test |
| 8 | `/app/caisse` | Ouvrir une session caisse |

---

## 5. Navigation et tableau de bord

### 5.1 Menu latéral (sidebar)

Organisé par groupes :

- **Catalogue** — Articles
- **Ventes** — Clients, Ventes, Devis, Facturation…
- **Pharmacie** — Lots, Ordonnances
- **Achats & Stock** — Fournisseurs, Achats, Stock…
- **Finances** — Dépenses, Caisse, Rapports
- **RH** — Paie, Présence
- **Système** — Utilisateurs, Configuration, Tickets, Abonnement

Seuls les modules **activés** et **autorisés** pour votre compte apparaissent.

### 5.2 Tableau de bord (`/app?tenant=CODE`)

- Chiffre d’affaires du jour et tendance (si Ventes actif)
- Graphique 7 derniers jours
- Raccourcis vers modules activés
- Factures récentes, alertes stock bas
- État caisse (session ouverte/fermée)
- Performance par boutique (multi-magasin)
- Widget pointage (Présence)

### 5.3 Sélecteur de boutique (multi-magasin)

Dans l’en-tête : choisir une boutique ou **Toutes les boutiques** (si permission `stores.view_all`).  
Filtre ventes, stock et rapports selon la boutique sélectionnée.

---

## 6. Configuration de la boutique

**Menu :** Système → **Configuration** (`/app/configuration`)

### 6.1 Identité visuelle

- **Logo principal** — factures, devis, en-têtes
- **Logo icône** — barre de navigation
- Formats acceptés : PNG, JPG, WebP, SVG

### 6.2 Paramètres généraux

| Paramètre | Description |
|-----------|-------------|
| Nom du magasin | Affiché sur documents et connexion |
| Message de bienvenue | Écran de connexion vendeur |
| Devise | Ex. XOF (FCFA) |
| Langue / fuseau | fr, Africa/Abidjan |
| Taux TVA | Taux par défaut (%) |
| Préfixes factures | INV, FTH (déclaré), FTN (non déclaré) |

### 6.3 En-tête et pied de page documents

À renseigner pour des factures conformes :

- NIU, RCCM, CNPS
- Adresse, BP, téléphone, e-mail, site web
- Coordonnées bancaires
- Pied de page personnalisé
- Modes de paiement par défaut

### 6.4 Relances clients

Corps de lettre type pour les **fiches de relance** (module Facturation).

### 6.5 Gestion des boutiques (multi-magasin)

Si activé par le Super Admin :

- Créer / modifier / désactiver des boutiques
- Définir la boutique **par défaut**
- Affecter chaque utilisateur à une boutique
- Affectation en masse

---

## 7. Utilisateurs, rôles et permissions

**Menu :** Système → **Utilisateurs** · **Rôles** · **Permissions**

### 7.1 Utilisateurs (`/app/users`)

- Créer un compte (nom, e-mail, mot de passe)
- Assigner un ou plusieurs **rôles**
- Activer / désactiver un compte
- Affecter une boutique (multi-magasin)

### 7.2 Rôles (`/app/roles`)

Rôles par défaut :

| Rôle | Accès typique |
|------|----------------|
| **admin** | Tout (bypass restrictions sidebar) |
| **cashier** | Ventes (voir + créer), Articles (voir) |

Créez des rôles métier : **Comptable**, **Magasinier**, **Acheteur**, etc.

### 7.3 Matrice permissions (`/app/permissions`)

Permissions au format `{module}.{action}` :

- `sales.view`, `sales.create`, `sales.modify_price`
- `items.view`, `items.view_cost`, `items.configure_list`
- `configuration.edit`, `stores.view_all`
- `roles.manage`

Cochez les cases par rôle. Un utilisateur **admin** voit tous les modules activés sans filtre permission.

---

## 8. Catalogue — Articles

**Menu :** Catalogue → **Articles** (`/app/items`)

**Prérequis :** module Articles installé.

### 8.1 Créer un article (`/app/items/create`)

- Référence, libellé, description
- Catégorie, marque, unité de vente
- Prix (détail, semi-gros, gros si applicable)
- Code-barres
- Coût d’achat (visible si permission `items.view_cost`)
- Stock initial (si module Stock actif)

### 8.2 Fiche article (`/app/items/{id}`)

Consultation, historique des mouvements, modification.

### 8.3 Configuration des colonnes (`/app/items/list-config`)

Personnaliser les colonnes visibles dans la liste (permission `items.configure_list`).

### 8.4 Bonnes pratiques

- Créer d’abord **catégories** et **unités** cohérentes
- Utiliser des références uniques
- Vérifier le stock après création (module Stock)

---

## 9. Clients

**Menu :** Ventes → **Clients** (`/app/clients`)

**Prérequis :** module Clients installé. Requis pour ventes à crédit, devis, factures.

### 9.1 Créer un client

- Type : particulier ou entreprise
- Coordonnées, adresses, contacts (plusieurs rôles : facturation, livraison…)
- Limite de crédit et conditions de paiement
- Commercial assigné
- Catégorie client, zone

### 9.2 Fiche client (`/app/clients/{id}`)

- Solde / encours (si module Dettes ou factures impayées)
- Notes internes, documents joints
- Rappels et relances
- Historique ventes et factures

### 9.3 Doublons (`/app/clients/duplicates`)

Détecter et **fusionner** les fiches clients en double (même téléphone, e-mail, etc.).

---

## 10. Ventes (Point de vente)

**Menu :** Ventes → **Ventes** (`/app/sales`)

**Prérequis :** Articles + Stock (recommandé) + Clients (pour crédit)

### 10.1 Nouvelle vente (`/app/sales/create`)

1. Rechercher un article (nom, référence, code-barres)
2. Ajouter au panier, ajuster quantités
3. Appliquer remise ligne ou globale (si autorisé)
4. Choisir le **client** (optionnel pour vente comptant)
5. **Paiement** :
   - Espèces
   - Orange Money / MTN Money
   - Crédit client (nécessite client + limite OK)
   - Paiement mixte (plusieurs modes)
6. **Valider** — stock décrémenté, ticket généré
7. **Imprimer** le reçu (`/app/sales/{id}/print`)

### 10.2 Ventes suspendues

Mettre une vente en attente (client reviendra) et la **reprendre** depuis la liste.

### 10.3 Modifier le prix à la caisse

Nécessite la permission `sales.modify_price`.

### 10.4 Retour vente POS (`/app/sales/{id}/return`)

Retour simplifié depuis une vente — liste des retours : `/app/sales/returns`.

### 10.5 Pharmacie

Avec **Lots** et **Ordonnances** : sélection du lot et lien ordonnance à la dispensation.

---

## 11. Devis et réservations

### 11.1 Devis (`/app/quotations`)

**Prérequis :** Clients + Articles

| Statut | Signification |
|--------|----------------|
| Brouillon | En cours de rédaction |
| Envoyé | Transmis au client |
| Accepté | Prêt à convertir en facture |
| Suspendu / Rejeté | Archivé |

**Actions :** créer, modifier, dupliquer, envoyer, accepter, rejeter, **imprimer** (`/print`), convertir en facture.

### 11.2 Réservations (`/app/reservations`)

**Prérequis :** Clients + Articles + Stock

1. Créer une réservation pour un client
2. Ajouter des lignes (quantités **bloquées** en stock)
3. **Convertir en devis** ou annuler
4. Détail : `/app/reservations/{id}`

---

## 12. Facturation et livraisons

**Menu :** Ventes → **Facturation** (`/app/invoices`)

**Prérequis :** Clients. Souvent après Devis.

### 12.1 Créer une facture

- Manuellement (`/app/invoices/create`)
- Depuis un **devis accepté**
- Types : avec / sans déclaration fiscale (préfixes FTH / FTN)

### 12.2 Cycle de vie facture

| Statut | Action |
|--------|--------|
| Brouillon | Édition libre |
| Émise | Numéro définitif, envoi client |
| Annulée | Contre-passation (selon règles métier) |

**Impression :** `/app/invoices/{id}/print`

### 12.3 Bons de livraison (`/app/invoices/deliveries`)

1. Créer un BL depuis devis ou facture
2. Workflow : **Brouillon → Confirmé** (sortie stock)
3. **Imprimer** le BL
4. Options d’impression configurables (quantités, prix, etc.)

### 12.4 Relances (`/app/invoices/collection-reminders`)

- Liste des créances échues
- Génération fiches de relance
- **Impression**, **PDF**, **export Excel**

---

## 13. Paiements factures et relances

**Menu :** Ventes → **Paiements factures** (`/app/invoice-payments`)

**Prérequis :** Facturation

1. Liste des factures avec solde restant
2. Saisir un paiement (`/app/invoice-payments/{invoice}/pay`)
3. **Imprimer le reçu** de paiement

Les paiements mettent à jour le solde client et peuvent alimenter la **Caisse** automatiquement.

---

## 14. Retours, avoirs et remboursements

**Menu :** Ventes → **Retours & Avoirs** (`/app/returns`)

**Prérequis :** Facturation (retour depuis facture)

### 14.1 Workflow retour

1. **Créer** — manuel ou depuis facture (`/app/returns/from-invoice/{id}`)
2. **Soumettre** pour validation
3. **Approuver** (niveau responsable)
4. **Réceptionner** les marchandises
5. **Contrôler** — impact stock

### 14.2 Avoirs (`/app/returns/credit-notes`)

Génération d’un **avoir** (note de crédit) imputable sur futures factures.

### 14.3 Remboursements (`/app/returns/refunds`)

Remboursement espèces / banque lié au retour.

### 14.4 Crédits client (`/app/returns/customer-credits`)

Portefeuille de crédit utilisable sur prochaines ventes.

---

## 15. Dettes clients

**Menu :** Ventes → **Dettes** (`/app/debts`)

**Prérequis :** Clients

- Enregistrer une dette manuelle
- Plan d’**échéancier**
- Saisir des **paiements** (`/app/debts/{id}/pay`)
- Suivi du solde restant

Complète le suivi des ventes à crédit et factures impayées.

---

## 16. Fournisseurs et achats

### 16.1 Fournisseurs (`/app/providers`)

- Fiche fournisseur, contacts
- Conditions de paiement (Comptant, Net 30, Net 60…)

### 16.2 Achats (`/app/purchases`)

**Prérequis :** Fournisseurs + Articles + Stock (réception)

| Étape | Statut |
|-------|--------|
| 1 | **Brouillon** — saisie lignes |
| 2 | **Confirmé** — commande validée |
| 3 | **Réception** — `/app/purchases/{id}/receive` — entrée stock (+ lots pharmacie) |
| 4 | **Annulé** — si non reçu |

**Impression** bon de commande : `/app/purchases/{id}/print`

---

## 17. Stock, inventaire et pertes

### 17.1 Stock (`/app/stock`)

**Prérequis :** Articles

| Action | URL | Description |
|--------|-----|-------------|
| Niveaux | `/app/stock` | Quantités par article / boutique |
| Ajustement | `/app/stock/adjust` | Correction manuelle (+ / −) |
| Transfert | `/app/stock/transfer` | Entre boutiques (multi-magasin) |
| Recherche | `/app/stock/lookup` | Trouver un article rapidement |
| Mouvements | `/app/stock/movements` | Historique complet |

Chaque vente, réception achat, BL confirmé, perte ou inventaire génère des **mouvements** tracés.

### 17.2 Inventaire (`/app/inventory`)

1. **Créer** un comptage (magasin ouvert possible)
2. Saisir les quantités comptées
3. **Finaliser** — écarts → ajustements automatiques

### 17.3 Pertes (`/app/losses`)

Enregistrer une perte (endommagé, expiré, vol, casse…) → confirmation → **sortie stock**.

---

## 18. Caisse

**Menu :** Finances → **Caisse** (`/app/caisse`)

Interface à onglets :

### 18.1 Session caisse

1. **Ouvrir** — saisir le fond de caisse initial
2. Encaissements ventes enregistrés automatiquement
3. **Entrées / sorties manuelles** (apports, retraits)
4. **Clôturer** — compter le physical, saisir montant compté, voir l’**écart**

### 18.2 Historique et sessions

- Journal filtrable par période et source
- Liste des sessions passées avec totaux

### 18.3 Exports

- Journal : **PDF** et **Excel**
- Session : **PDF** et **Excel** par session

> Ouvrir une session caisse **avant** la journée de vente est recommandé pour un suivi fiable.

---

## 19. Dépenses et rapports

### 19.1 Dépenses (`/app/expenses`)

- Catégories : loyer, utilities, salaires, transport, marketing…
- Workflow : brouillon → **soumis** → **approuvé** / **rejeté**

### 19.2 Rapports (`/app/reporting`)

**Prérequis :** modules source selon onglets

| Onglet | Contenu |
|--------|---------|
| Finances | CA, COGS, marge, bénéfice, paie, achats |
| Devis | Pipeline commercial |
| Factures | Émissions, impayés |
| Ventes POS | Performance caisse |
| Top produits | CA et quantités |
| Dépenses | Par catégorie |
| Pertes | Par motif |
| Clients | Meilleurs clients |

Indicateurs avec **tendance vs période précédente**, dettes en retard, ruptures stock.

---

## 20. Pharmacie (lots et ordonnances)

**Types vendeur :** pharmacie · **Prérequis :** Articles + Stock

### 20.1 Lots / Péremption (`/app/batches`)

- Enregistrer numéro de lot et date de péremption
- Lié aux réceptions achat et ventes
- Alertes produits proches expiration (via stock / rapports)

### 20.2 Ordonnances (`/app/prescriptions`)

1. Saisir ordonnance (client, médecin, lignes)
2. À la **vente POS**, lier l’ordonnance pour **dispenser**
3. Traçabilité réglementaire

**Ordre d’activation pharmacie :** Articles → Stock → Lots → Ordonnances → Ventes

---

## 21. Paie et présence (RH)

### 21.1 Paie (`/app/payroll`)

- Gérer les **employés** et historique salarial
- Créer une **période de paie**
- Calculer les lignes, valider, marquer **payé**
- **Imprimer bulletin** (`/app/payroll/{run}/payslip/{line}`)
- Module **congés** : demandes et approbations (`/app/payroll/leaves`)

### 21.2 Présence (`/app/attendance`)

- **Pointer** arrivée / départ (widget en barre supérieure)
- Fiches individuelles et équipe
- **Impression** fiches de présence

---

## 22. Tickets et abonnement vendeur

### 22.1 Tickets (`/app/tickets`)

Suivi incidents internes : ouverture → itérations → résolution → clôture.

### 22.2 Abonnement (`/app/subscription`)

Visible par les utilisateurs vendeur :

- Plan actuel, date de fin de période
- Solde créditeur
- Souscrire ou prolonger (si solde disponible)
- Changer de plan

Géré côté Super Admin via **Abonnement vendeur**.

---

## 23. Multi-magasin

### Activation (Super Admin)

- Cocher **Multi-magasin** à la création du vendeur, ou
- Activer dans **Paramètres vendeur** → job de configuration automatique

### Configuration (tenant)

Configuration → **Gestion des boutiques** : créer boutiques, affecter utilisateurs.

### Utilisation

- Sélecteur boutique dans l’en-tête
- Stock, ventes, rapports **par boutique**
- **Transferts** stock entre boutiques
- Permission `stores.view_all` pour vue consolidée

---

## 24. Impressions et exports

| Document | Accès |
|----------|--------|
| Reçu vente POS | Ventes → détail → Imprimer |
| Devis | Devis → Imprimer |
| Facture | Facturation → Imprimer |
| Bon de livraison | BL → Imprimer |
| Reçu paiement facture | Paiements → Imprimer |
| Bon de commande achat | Achats → Imprimer |
| Fiche relance | Relances → Print / PDF / Excel |
| Bulletin de paie | Paie → Imprimer |
| Fiche présence | Présence → Imprimer |
| Journal / session caisse | Caisse → Export PDF / Excel |
| Événements modules (admin) | Module Events → Export |

**Personnalisation :** Configuration → logos, en-tête légal (NIU, RCCM…), pied de page.

**Astuce :** utilisez **Ctrl+P** dans le navigateur ; les modèles sont optimisés pour l’impression.

---

## 25. Ordre d’activation des modules

### Commerce de détail (retail)

```
Articles → Stock → Clients → Ventes → Caisse
         → Fournisseurs → Achats
         → Devis → Facturation → Paiements factures
         → Retours (si SAV)
         → Dettes (si crédit)
         → Dépenses → Rapports
```

### Pharmacie

```
Articles → Stock → Lots → Ordonnances → Ventes
+ modules commerce selon besoin (Facturation, Achats…)
```

### Graphe des dépendances

```
[CORE] Utilisateurs + Configuration
    │
[Articles] ─────────────────────────────────────┐
    │                                              │
[Clients] [Fournisseurs] [Stock] [Dépenses]       │
    │         │            │                       │
    │    [Achats]     [Inventaire] [Pertes]       │
    │                   [Lots]*                    │
    ├─[Ventes]**                                    │
    ├─[Devis] → [Facturation] → [Paiements]       │
    ├─[Réservations]                                │
    ├─[Dettes]                                      │
    └─[Retours] ← factures                          │
         [Caisse] [Rapports] [Paie] [Présence]    │
         [Ordonnances]* → ventes                   │
* pharmacie  ** une seule famille « ventes »
```

---

## 26. Dépannage utilisateur

| Problème | Solution |
|----------|----------|
| « Module not enabled » / menu absent | Super Admin → Packages → Installer le module pour ce vendeur |
| Pas de permission | Admin vendeur → Rôles / Permissions → accorder `{module}.view` |
| Connexion vendeur impossible | Vérifier `?tenant=CODE` dans l’URL |
| Redirection abonnement | Super Admin → renouveler abonnement ou créditer solde |
| Stock négatif / vente bloquée | Stock → ajuster ou réception achat |
| Module « Installé » mais menu absent | Vérifier permission utilisateur ; se déconnecter/reconnecter |
| Packages affiche « Installer » alors que actif | Rafraîchir la page (correctif cache déployé) |
| Logo absent sur factures | Configuration → téléverser logo ; vérifier `/storage/` accessible |
| Provisionnement vendeur en échec | Admin → Santé vendeurs → **Relancer** |
| Doublons clients | Clients → Doublons → fusionner |

### Contacts et support

- **Super Admin plateforme** : problèmes vendeurs, modules, abonnements
- **Admin boutique** : utilisateurs, permissions, paramètres métier
- **Documentation technique** : [docs/README.md](./README.md) · [Deploy](../deploy/docker/DEPLOY.md)

---

*Fin du manuel utilisateur Inov-Com — document vivant, à mettre à jour lors de l’ajout de nouveaux modules.*
