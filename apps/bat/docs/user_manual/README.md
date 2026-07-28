# BPROO ERP — Manuel utilisateur

> **Système :** ERP multi-tenant pour entreprises de construction, rénovation et maintenance du bâtiment  
> **Public :** Administrateurs plateforme, gestionnaires, commerciaux, techniciens, comptables

---

## Processus couverts

### Administration plateforme (Admin)
| # | Manuel | Processus |
|---|--------|-----------|
| 01 | [Onboarding plateforme](01_ADMIN_ONBOARDING.md) | Créer un tenant, activer les modules, gérer les abonnements |
| 02 | [Analytique & supervision](02_ADMIN_ANALYTIQUE.md) | Suivi santé, statistiques plateforme, alertes |

### Configuration tenant (Responsable)
| # | Manuel | Processus |
|---|--------|-----------|
| 03 | [Configuration entreprise](03_CONFIGURATION.md) | Paramétrage branding, langue, TVA, coordonnées |
| 04 | [Utilisateurs & droits](04_UTILISATEURS_DROITS.md) | Créer utilisateurs, rôles, matrice de permissions |
| 05 | [Catalogue articles](05_CATALOGUE_ARTICLES.md) | Créer produits/services, unités, tarifs |

### Cycle commercial (Commercial / Gestionnaire)
| # | Manuel | Processus |
|---|--------|-----------|
| 06 | [Gestion clients](06_CLIENTS.md) | Créer fiche client, contacts, historique |
| 07 | [Offres commerciales](07_OFFRES.md) | Réception demande, création offre, qualification |
| 08 | [Devis](08_DEVIS.md) | Élaborer, envoyer, suivre, accepter/refuser un devis |
| 09 | [Cycle complet : Offre → Projet](09_CYCLE_COMMERCIAL_COMPLET.md) | Scénario de bout en bout |

### Gestion de projet (Chef de projet)
| # | Manuel | Processus |
|---|--------|-----------|
| 10 | [Projets](10_PROJETS.md) | Créer, planifier, affecter, suivre, clôturer un projet |
| 11 | [Suivi terrain](11_SUIVI_TERRAIN.md) | Rapports journaliers, photos, PV de réception client |
| 12 | [Planning & calendrier](12_PLANNING.md) | Rendez-vous, visites, réunions, jalons |

### Facturation (Comptable / Gestionnaire)
| # | Manuel | Processus |
|---|--------|-----------|
| 13 | [Facturation](13_FACTURATION.md) | Créer facture, enregistrer paiement, gérer impayés |
| 14 | [Achats](14_ACHATS.md) | Bons de commande, approbation, réception |

### Maintenance (Responsable SAV / Technicien)
| # | Manuel | Processus |
|---|--------|-----------|
| 15 | [Contrats de maintenance](15_MAINTENANCE_CONTRATS.md) | Créer et gérer les contrats SLA |
| 16 | [Ordres de maintenance](16_MAINTENANCE_ORDRES.md) | Demande corrective, dispatch, clôture |
| 17 | [Interventions terrain](17_INTERVENTIONS.md) | Rapport technicien, signature client |

### Gestion documentaire (Tous)
| # | Manuel | Processus |
|---|--------|-----------|
| 18 | [Documents (DMS)](18_DOCUMENTS.md) | Upload, organisation, archivage par entité |

### Rapports & Audit (Direction / Responsable)
| # | Manuel | Processus |
|---|--------|-----------|
| 19 | [Rapports financiers](19_RAPPORTS.md) | AR Aging, revenus, pipeline devis, rentabilité, techniciens |
| 20 | [Journal d'audit](20_AUDIT.md) | Traçabilité, filtrage, consultation historique |

---

## Données de test communes

Les manuels utilisent des données fictives cohérentes entre elles :

| Donnée | Valeur |
|--------|--------|
| Tenant de test | `kreobat` |
| Client principal | BIMEX SARL, Douala |
| Client secondaire | IMMO TRUST SA, Yaoundé |
| Responsable | Admin Kreobat (admin@kreobat.cm) |
| Commercial | Jean Dupont |
| Technicien | Pierre Martin |
| Devise | XOF (Franc CFA) |
| TVA | 19,25% |

---

## Légende des niveaux d'accès

| Icône | Rôle requis |
|-------|------------|
| 🔴 **Admin plateforme** | Accès admin système |
| 🟠 **Admin tenant** | Rôle Admin dans le tenant |
| 🟡 **Gestionnaire** | Rôle avec permissions étendues |
| 🟢 **Utilisateur** | Rôle utilisateur standard |

---

## Comment utiliser ces manuels

1. Chaque manuel décrit **un processus complet** avec étapes numérotées
2. Les **Prérequis** indiquent ce qui doit exister avant de commencer
3. Les **Données de test** sont prêtes à copier-coller dans les formulaires
4. Le **Résultat attendu** permet de valider que le processus fonctionne
5. Les **Cas particuliers** couvrent les variantes et erreurs courantes
