# Développement – BPROO ERP

## Prérequis

- PHP 8.2+
- Composer
- PostgreSQL (ou MySQL selon config)
- Node.js / npm (pour Vite)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configurer .env : APP_*, DB_*, etc.
php artisan migrate
php artisan modules:sync
```

## Commandes utiles

| Commande | Description |
|----------|-------------|
| `php artisan modules:sync` | Synchronise `config/modules.php` vers la table `modules`. |
| `php artisan tenants:migrate` | Exécute les migrations sur les bases tenant. |
| `php artisan tenants:seed` | Seed les bases tenant (ex. rôles par défaut). |

(Voir aussi les commandes de provisioning des tenants si présentes dans le projet.)

## Structure des paquets (modules)

Les modules vivent sous `packages/inovcom/<nom>/` :

- `src/` : ServiceProvider, modèles, contrôleurs, Livewire.
- `src/<Nom>Module.php` : implémentation de `ModuleLifecycle` (install/uninstall).
- `database/migrations/` : migrations publiées avec un tag (ex. `inovcom-items-migrations`).
- `resources/views/` : vues (namespace `inovcom-<nom>`).

Dans `config/modules.php` :

- Ajouter une entrée (clé, label, description, `route_name`, `lifecycle_handler`, `core`, `order`, etc.).
- Exécuter `php artisan modules:sync`.

Le ServiceProvider du module doit utiliser le trait `LazyModuleBoot` et vérifier `shouldBootModule()` avant d’enregistrer routes, vues et bindings.

## Ajouter un module « Client »

1. Créer le package `packages/inovcom/clients/`.
2. Implémenter `ClientsApi` (kernel) et l’enregistrer dans le ServiceProvider.
3. Implémenter `ModuleLifecycle` pour install/uninstall (migrations tenant si besoin).
4. Déclarer le module dans `config/modules.php` avec `'core' => true` et `'order' => 10` pour le mettre en premier dans le menu.
5. Enregistrer les routes (ex. `tenant.clients.index`, `tenant.clients.create`, etc.).
6. Lancer `php artisan modules:sync`. Activer le module pour les tenants concernés depuis l’admin.

## Bonnes pratiques

- Utiliser la connexion `tenant` pour tout modèle métier côté tenant : `Model::on('tenant')` ou modèle étendant `TenantModel`.
- Ne pas accéder directement aux modèles d’un autre module ; passer par les contrats kernel (`ClientsApi`, `ItemsApi`).
- Tester avec plusieurs tenants et avec modules activés/désactivés.
- Traductions : utiliser `__('key')` avec des clés dans `lang/fr.json` et `lang/en.json`.
