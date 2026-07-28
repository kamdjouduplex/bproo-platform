# MODULE PRESSING
## Extension du noyau ERP existant

Version : 1.0

---

# Objectif

Développer un ou plusieur modules complet de gestion de pressing qui s'intègre au noyau inv-com existant.

Le module ne doit pas être développé comme une application indépendante.

Il doit respecter :

- l'architecture existante
- les composants UI existants
- le système d'authentification existants
- les permissions existants
- les notifications existants
- les conventions de codage existants
- les services communs existants
- le système de facturation existant existants
- les composants Livewire existants existants

L'objectif est de permettre la gestion complète du cycle de vie des vêtements, depuis leur réception jusqu'à leur livraison.

A cet effet je ne connais pas exactement combien de module suplementaire il faut developer, puis quil en a deja un bon nombre qui existe et qu'on doit concerver et d'autre quon doit deinstaller pour les tenants qui gere les pressings

---

# Architecture

Les nouveaux modules doit être entièrement modulaire.

Nom du package :

pressing

Il devra être composé des modules suivants :

- Clients 
- Réception
- Commandes
- Articles 
- Workflow
- Paiements
- Livraison
- Notifications
- Rapports
- Paramétrage

---
# Module Agences
Permet de gérer :

- création
- modification
- suppression
- historique
Informations :
- code agence
- nom agence
- pays
- vile
- localisation
- telephone
- email
- date
- heure
- responsable agence (qui est un employee)
tout informantion dans le system doit etre rattache a une agences pour que ont puisse avoir les stats globals si on gerer plusieurs agences

# Module Clients

Permet de gérer :

- création
- modification
- suppression
- historique

Informations :
- code agence (agence dans la quel il a ete enregistrer et la recherche d'un client ne se limite pas seuelement a une agence)
- nom
- prénom
- WhatsApp
- téléphone (facultatif)
- email (facultatif)
- adresse
- observations

Afficher :

- nombre total de commandes
- chiffre d'affaires
- commandes en cours
- dernière visite

---

# Module Réception

Créer une commande de pressing.

Informations :

- client
- code agence
- employé réceptionnaire
- date
- heure

Une commande contient plusieurs articles.

---

# Module Articles

Chaque article possède :

- type
- quantité
- couleur
- marque
- taille
- observations
- état à la réception
- photos (optionnel)

Exemple de type (cette liste doit etre parametrable):

Chemise

Costume

Pantalon

Robe

Rideaux

Couverture

Tapis

Chaussures

etc.

---

# Tarification

Chaque type d'article possède un tarif.

Le système calcule automatiquement :

- sous-total
- remise
- TVA
- total
- reste à payer

Le tarif doit être paramétrable.

---

# Paiements

Supporter :

- espèces
- Mobile Money
- carte bancaire
- virement

Permettre :

- paiement complet
- paiement partiel
- paiement multiple

Historiser tous les paiements.

---

# Workflow

Chaque commande doit suivre un workflow configurable.

Exemple :

Réception

↓

Tri

↓

Lavage

↓

Séchage

↓

Repassage

↓

Contrôle qualité

↓

Emballage

↓

Prêt

↓

Livré

Le workflow ne doit PAS être codé en dur.

Chaque pressing pourra créer ses propres étapes.

Chaque étape contient :

- nom
- couleur
- ordre
- icône
- durée estimée

---

# Tableau Kanban

Créer une vue Kanban.

Chaque colonne représente une étape.

Les cartes doivent pouvoir être déplacées par Drag & Drop.

Le déplacement met automatiquement à jour le statut.

---

# Historique

Conserver un journal complet.

Exemple :

08:15

Commande créée

08:20

Lavage

09:45

Repassage

11:00

Contrôle

11:10

Prête

Utilisateur ayant effectué chaque action.

---

# Affectation

Une commande peut être affectée à :

- un employé
- une équipe

Afficher les tâches de chaque employé.

---

# Notifications

Créer un moteur de notifications.

Déclencheurs :

Commande créée

Commande prête

Commande livrée

Paiement reçu

Paiement restant

Commande en retard

Les notifications doivent supporter :

- WhatsApp
- SMS
- Email
- In App (Pour les employees)

Les modèles de messages doivent être personnalisables.

Exemple :

Bonjour {{client}}

Vos vêtements sont prêts.

Vous pouvez passer les récupérer.

Merci pour votre confiance.

---

# QR Code

Chaque commande reçoit un QR Code.

Le QR Code permet :

- consultation rapide
- changement de statut
- impression

---

# Impression

Créer :

- reçu dépôt
- facture
- ticket
- étiquette QR Code

---

# Livraison

Permettre :

livraison agence

livraison domicile

Gestion :

chauffeur

véhicule

signature

photo

date

heure

---

# Tableau de bord

Créer un Dashboard avec :

Commandes du jour

Articles reçus

Articles livrés

Commandes en attente

Commandes en retard

CA du jour

CA du mois

Paiements reçus

Reste à encaisser

---

# Rapports

Rapports par :

jour

semaine

mois

année

client

article

employé

agence

Exporter :

PDF

Excel

CSV

---

# Gestion des stocks

Consommables :

lessive

savon

parfum

cintres

emballages

étiquettes

Créer :

entrées

sorties

inventaires

alertes de seuil

---

# Fidélité

Créer un système de points.

Exemple :

10 commandes

=

1 lavage gratuit

---

# Abonnements

Supporter :

Hôtels

Restaurants

Hôpitaux

Entreprises

Facturation mensuelle.

---

# Paramétrage

Créer des écrans permettant de configurer :

Types de vêtements

Tarifs

Workflow

Délais

Messages

Taxes

Agences

Employés

Types de paiement

---

# Sécurité

Toutes les opérations doivent respecter les permissions du système existant.

Ne jamais dupliquer le système de rôles.

Réutiliser le module RBAC existant.

---

# Contraintes techniques

Le développement doit impérativement :

- réutiliser les composants existants
- respecter les conventions Laravel
- respecter les conventions Livewire
- utiliser les Services existants
- utiliser les Repositories existants
- utiliser les Policies existantes
- utiliser les Notifications existantes
- utiliser les Events existants
- utiliser les Migrations existantes

Ne jamais créer de code dupliqué.

Toujours privilégier la réutilisation.

---

# Qualité

Chaque écran doit être :

responsive

rapide

modulaire

réutilisable

maintenable

Chaque fonctionnalité doit être testable indépendamment.

---

# Objectif final

Construire un véritable ERP de gestion de pressing permettant de suivre chaque vêtement depuis sa réception jusqu'à sa livraison, tout en offrant une excellente expérience utilisateur, une automatisation maximale (notifications, suivi, paiements) et une architecture suffisamment flexible pour s'adapter aux processus de tout type de pressing.

Tres important je dirais cruxial meme.
l'architecture actuel du code est:
- un portail admin a travers le quel je creer des tenants(chaque pressing que nous contratons) et lui active les modules necessaires et son abonement.
- un portail tenant accessible a travers son code example http://localhost:8000/app?tenant=demo-pressing
Cest ici qui a la gestion entier tel que dans le code actuel. ca base de donnee est independante de la base de donnee admin.

Si tu as des quelquestion tu peux me leur poser