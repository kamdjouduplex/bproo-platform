# Specification fonctionnelle - BPROO ERP

Document reconcilie avec le cahier des charges. Cible : **entreprise du batiment** (construction neuve, renovation, maintenance).

## 1. Contexte et objectifs

- Centraliser **clients**, **projets** et **interventions**.
- Suivre le cycle des offres (reception a cloture chantier).
- Gerer maintenances ponctuelles ou recurrentes.
- Automatiser devis, bons d'achat, plannings, factures.
- Rentabilite, collaboration, rapports et tableaux de bord.

## 2. Client au centre

Le **client** est l'entite centrale. Toute offre (projet ou maintenance) est liee a un client. Projets, ordres de maintenance, devis, factures et documents sont associes au client. Les modules s'appuient sur le **module Client** et le contrat kernel `ClientsApi`.

## 3. Offres

- **Projet** : construction neuve, gros oeuvre, extension.
- **Maintenance** : reparation, depannage, peinture, plomberie.

## 4. Processus Projet

Reception offre -> Visite terrain -> Documents techniques -> Devis -> Si accepte : creation client si besoin, creation projet, dossier execution, planning, facturation, bons d'achat, suivi jusqu'a cloture. Si refuse : archivage et motif.

## 5. Processus Maintenance

Reception -> Client si besoin -> Ordre de maintenance -> Bons d'achat -> Facturation -> Affectation technicien -> Realisation -> PV signe.

## 6. Modules ERP

| Module | Role |
|--------|------|
| **Client** | Fiche client, contacts, historique. Central. |
| Offres | Demandes, categorisation Projet/Maintenance. |
| Planning | Rendez-vous, visites, Gantt. |
| GED | Documents (plans, permis, contrats, PV). |
| Devis | Devis, catalogue, PDF. |
| Projet | Projets, phases, equipes, couts. |
| Facturation | Factures, relances. |
| Achats | Bons d'achat, fournisseurs. |
| Maintenance | Ordres, planification, cloture. |
| Suivi terrain | Rapports chantier, photos, PV. |
| Reporting | Rapports par domaine. |
| Dashboard | Tableaux de bord par role. |

Modules techniques : Utilisateurs, Configuration (core), Articles (optionnel).

## 7. Interface

- Admin et Portail tenant : **menu lateral**.
- Multilingue : **francais** par defaut, **anglais** en secours.
