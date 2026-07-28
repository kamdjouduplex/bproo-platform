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

- **[Setup Guide](docs/SETUP.md)** – Setup and first tenant
- **[Developer Guide](docs/DEVELOPER_GUIDE.md)** – Building modules
- **[Architecture](docs/architecture.md)** – Multi-tenancy, modules, auth
- **[Module Packages](docs/module-packages.md)** – Package layout and config
- **[Modules](docs/modules.md)** – Implemented and planned entities
- **[UI Guidelines](docs/ui-guidelines.md)** – Design standards
- **[Docs index](docs/README.md)** – Full list of docs (current and reference)

## Module System

Modules are Composer packages located in `packages/inovcom/`.

### Core Modules

- **users**: RBAC (users, roles, permissions). Always available per tenant. Run `php artisan users:install-tenant <code>` for existing tenants.
- **configuration**: Shop name, welcome message, portal settings. Always available (package: `branding`, route: `/configuration`).

### Normal Modules

- **items**: Product catalog (items, categories, brands, units). Optional per tenant; enable via Admin → Packages or Activation.

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
│   ├── Console/Commands/  # Artisan (modules:sync, tenant:migrate, users:install-tenant, etc.)
│   ├── Http/               # Controllers, middleware (EnsureModuleEnabled, SetTenantConnection)
│   ├── Jobs/               # InstallModuleJob, UninstallModuleJob, ProvisionTenantJob
│   ├── Livewire/Admin/     # Admin: Tenants, Packages, Modules, Events, Health, Settings
│   ├── Models/             # Tenant, Module, TenantModule, ModuleEvent, TenantSetting
│   └── Services/           # TenantManager, ModuleManager, ModuleRegistry, ModuleVersionService
├── packages/inovcom/       # Module packages
│   ├── kernel/             # Contracts (ModuleLifecycle, ItemsApi, ClientsApi), TenantModel, LazyModuleBoot
│   ├── users/              # Users module (core) – RBAC
│   ├── branding/          # Configuration module (core) – route /configuration
│   └── items/              # Items module – catalog
├── config/
│   └── modules.php        # Module registry (users, configuration, items)
├── database/migrations/    # System + tenant + tenant_modules + module_events
├── docs/                   # Documentation (see docs/README.md)
└── resources/
    ├── css/app.css         # App styles (no Tailwind)
    └── views/              # Layouts, livewire, errors
```

## Key Features

### Multi-Tenancy

- **System Database**: Stores tenant metadata and module configurations
- **Tenant Databases**: Each tenant has isolated PostgreSQL database
- **Connection Resolution**: Automatic via middleware based on subdomain or query parameter

### Module Management

- Modules registered in `config/modules.php` (with optional `permission` for RBAC)
- **Admin → Packages**: Install/uninstall modules per tenant (uses queue: run `php artisan queue:work`)
- **Admin → Activation**: Manage which modules are enabled per tenant
- Core modules (users, configuration) always available; normal modules optional
- Run `php artisan modules:sync` after changing `config/modules.php`

### Authentication

- **System Admin**: Manages tenants and modules
- **Tenant Users**: Access tenant-specific features
- Separate auth guards: `auth()` and `auth('tenant')`

### Routes

- **System**: `/admin/*` – dashboard, tenants, Packages, Modules, Activation, Health, Events
- **Tenant**: `/app/*?tenant=<code>` – dashboard and module routes
- Module routes use `module:{key}` middleware (EnsureModuleEnabled; checks permission when set in config)

## Artisan Commands

```bash
# Module management
php artisan modules:sync              # Sync modules from config (run after editing config/modules.php)
php artisan modules:version-sync      # Sync versions from composer.json
php artisan modules:check-updates [tenant]  # Check for module updates

# Tenant management
php artisan tenant:migrate <code>      # Run migrations for tenant
php artisan tenant:seed <code>         # Seed tenant database
php artisan users:install-tenant <code>   # Install users module (roles/permissions) for tenant

# Queue (required for Packages install/uninstall)
php artisan queue:work                 # Process install/uninstall jobs
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
