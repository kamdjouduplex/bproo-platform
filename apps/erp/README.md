# Inov-Com

Innovation Commercial — multi-vendor, modular POS platform designed for Africa.

## Product Goals

- **Multi-vendor**: Separate database per vendor (tenant)
- **Modular system**: Enable/disable modules per vendor via super-admin portal
- **Offline-first**: PWA with sync capabilities (planned)
- **Multilingual**: French (default) and English support
- **Desktop-first UI**: Responsive design for tablet/mobile

## Technology Stack

- **Backend**: Laravel 10.10+
- **Frontend**: Livewire 4.0
- **Database**: PostgreSQL 12+
- **UI Framework**: Custom CSS (Inter font, modern design)
- **Notifications**: mckenziearts/laravel-notify
- **Authentication**: Laravel Sanctum

## Quick Start

For detailed setup instructions, see [docs/SETUP.md](docs/SETUP.md).

```bash
# Clone the repository
git clone <repository-url> revo-com
cd revo-com

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database in .env (PostgreSQL)
# Then run migrations
php artisan migrate
php artisan modules:sync

# Build frontend assets
npm run dev

# Start development server
php artisan serve
```

## Documentation

### Développement

- **[Manuel utilisateur](docs/MANUEL-UTILISATEUR.md)** — guide complet admin + boutique (tous modules)
- **[Setup Guide](docs/SETUP.md)** — installation locale
- **[Developer Guide](docs/DEVELOPER_GUIDE.md)** — créer des modules
- **[Architecture](docs/architecture.md)** — vue d’ensemble
- **[Module Packages](docs/module-packages.md)** — structure des packages
- **[Modules](docs/modules.md)** — modules et entités
- **[UI Guidelines](docs/ui-guidelines.md)** — standards UI
- **[Abonnements](docs/SUBSCRIPTION_SYSTEM.md)** — système d’abonnement vendeurs
- **[Contributing](docs/CONTRIBUTING.md)** — contribution au projet

### Production (VPS)

- **[Deploy Guide](deploy/docker/DEPLOY.md)** — installation et mises à jour sur `erp.afroinov.com`
- **[Guide Super Admin PDF](docs/pdf/GUIDE-SUPER-ADMIN.pdf)** — administration plateforme (PDF)

## Module System

Modules are Composer packages located in `packages/inovcom/`.

### Core Modules

- **users**: RBAC system (users, roles, permissions) - always available
- **branding**: Shop customization (logo, welcome message) - always available

### Normal Modules

- **items**: Product catalog (items, categories, brands, units) - optional

### Adding a Module

See [DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md) for complete instructions.

Quick steps:
1. Create package in `packages/inovcom/your-module/`
2. Add to `composer.json` repositories and require
3. Register service provider in `config/app.php`
4. Add module entry to `config/modules.php`
5. Run `composer update` and `php artisan modules:sync`

## Project Structure

```
revo-com/
├── app/                    # Application code
│   ├── Console/           # Artisan commands
│   ├── Http/              # Controllers, middleware
│   ├── Models/            # System models (Tenant, Module)
│   └── Services/          # Business logic services
├── packages/inovcom/      # Module packages
│   ├── kernel/            # Shared contracts and base classes
│   ├── users/             # Users module (core)
│   ├── branding/          # Branding module (core)
│   └── items/             # Items module
├── config/                # Configuration files
│   └── modules.php        # Module registry
├── database/
│   └── migrations/tenant/ # Tenant migrations
├── docs/                  # Documentation
└── resources/
    ├── css/               # Stylesheets
    └── views/             # Blade templates
```

## Key Features

### Multi-Tenancy

- **System Database**: Stores tenant metadata and module configurations
- **Tenant Databases**: Each tenant has isolated PostgreSQL database
- **Connection Resolution**: Automatic via middleware based on subdomain or query parameter

### Module Management

- Modules registered in `config/modules.php`
- Can be enabled/disabled per tenant
- Core modules always available
- Normal modules optional per tenant

### Authentication

- **System Admin**: Manages tenants and modules
- **Tenant Users**: Access tenant-specific features
- Separate auth guards: `auth()` and `auth('tenant')`

### Routes

- **System Routes**: `/admin/*` - Admin portal
- **Tenant Routes**: `/app/*?tenant=<code>` - Tenant portal
- Module routes protected by `module:{key}` middleware

## Artisan Commands

```bash
# Module management
php artisan modules:sync              # Sync modules from config

# Tenant management
php artisan tenant:migrate <code>     # Run migrations for tenant
php artisan tenant:seed <code>        # Seed tenant database
php artisan users:install-tenant <code>  # Install users module for tenant
```

## Development Workflow

1. **Make changes** to code
2. **Clear caches** if needed: `php artisan cache:clear`
3. **Watch frontend**: `npm run dev`
4. **Test** your changes

## Environment Configuration

Key environment variables:

```env
APP_NAME="Inov-Com"
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=pgsql
DB_DATABASE=inovcom_system
```

See `.env.example` for complete configuration.

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](docs/CONTRIBUTING.md) for guidelines.

Quick checklist:
1. Read [DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md) for module development
2. Follow coding standards (Laravel conventions, PSR-12)
3. Test your changes thoroughly
4. Update documentation if needed
5. Create clear commit messages

## License

MIT

## Support

For questions or issues:
- Review documentation in `docs/`
- Check existing modules for examples
- Review Laravel and Livewire documentation
