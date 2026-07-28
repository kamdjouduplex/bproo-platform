# Architecture – BPROO ERP

## 1. Vue d’ensemble

- **Application Laravel** multi-tenant : une base de données par tenant (entreprise).
- **Modules** : paquets indépendants, activables/désactivables par tenant (sauf modules « core »).
- **Client-centric** : le module Client et le contrat `ClientsApi` (kernel) sont le pivot ; les autres modules s’y raccordent.

## 2. Multi-tenant

- **Tenant** : une entreprise (ex. une société de construction). Chaque tenant a :
  - `name`, `code`, base dédiée (`db_name`, `db_host`, …).
  - Statut de provisioning (`completed`, `provisioning`, `failed`).
  - Modules activés (table pivot `tenant_modules`).
  - Paramètres (table `tenant_settings`).
- **Contexte** : le middleware `tenant` et la connexion `tenant` permettent de cibler la base du tenant courant (segment URL ou sous-domaine selon configuration).
- **Admin** : pas de tenant ; connexion à la base « système » (tenants, modules, santé, etc.).

## 3. Portails

| Portail | Préfixe URL | Usage |
|---------|-------------|--------|
| Admin | `/admin` | Gestion plateforme : tenants, modules, packages, santé. |
| Tenant | `/app/{tenant}` | Espace entreprise : clients, offres, projets, maintenances, etc. |

Les deux portails utilisent un **menu latéral** (sidebar) pour la navigation.

## 4. Noyau (Kernel) – packages/inovcom/kernel

- **Contrats** (interfaces) pour découpler les modules :
  - `ModuleLifecycle` : `install(Tenant)`, `uninstall(Tenant)`.
  - `ClientsApi` : `findClient`, `findClientByCode`, `getCreditLimit`, `getCurrentBalance`, `canMakePurchase`, `getActiveClients`, `clientExists`.
  - `ItemsApi` : API catalogue articles (pour devis, achats).
- **TenantModel** : base pour les modèles dont les données sont en base tenant.
- **LazyModuleBoot** : les ServiceProviders des modules ne chargent routes/vues que si le module est activé pour le tenant courant.

Les modules (Client, Offres, Projet, etc.) implémentent ces contrats et s’enregistrent dans `config/modules.php`.

## 5. Modules

- **Définition** : `config/modules.php` (clé, label, description, `route_name`, `lifecycle_handler`, `core`, `enabled_by_default`, `order`, etc.).
- **Sync** : `php artisan modules:sync` met à jour la table `modules` à partir de la config.
- **Activation** : par tenant dans l’admin (Tenant → Modules) ; stocké dans `tenant_modules`.
- **Core** : modules toujours présents (users, configuration ; clients prévu en core métier). Les migrations « core » sont exécutées au provisioning.
- **Ordre du menu** : les liens du menu tenant sont triés par `order` (config) pour afficher **Clients** en premier.

## 6. Routes et menu

- **Admin** : routes dans `routes/web.php` (prefix `admin`), menu défini dans le layout (sidebar).
- **Tenant** : routes enregistrées par les ServiceProviders des modules (prefix `app`, middlewares `tenant`, `auth:tenant`, `module:{key}`). Le **ModuleManager** construit les liens du menu à partir des modules activés et de la config (ordre, route existante).

## 7. Internationalisation

- **Locale par défaut** : `fr` (`config/app.php`).
- **Fallback** : `en`.
- Fichiers de traduction : `lang/fr.json`, `lang/en.json`. Le sélecteur de langue dans la sidebar enregistre le choix en session.

## 8. Évolutions prévues

- Implémentation du **module Client** (package dédié, implémentation de `ClientsApi`).
- Modules métier : Offres, Devis, Projet, Maintenance, Facturation, Achats, GED, Planning, Suivi terrain, Reporting, Dashboard.
- Chaque module peut déclarer des événements (ex. `ClientCreated`) pour une architecture événementielle entre modules.
