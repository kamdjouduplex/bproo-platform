# Inov-Com Architecture

## Overview

Inov-Com is a multi-tenant, modular POS platform designed for the African market. It uses a database-per-tenant architecture with a flexible module system.

## Goals

- **Multi-vendor**: Separate database per vendor (tenant)
- **Modular**: Enable/disable modules per vendor via admin portal
- **Multilingual**: French (default) and English support
- **Offline-first**: PWA with sync capabilities (planned)
- **Self-hosted**: Predictable scaling and deployment

## Technology Stack

- **Backend**: Laravel 10.10+
- **Frontend**: Livewire 4.0
- **Database**: PostgreSQL 12+
- **UI**: Custom CSS framework with Inter font
- **Notifications**: mckenziearts/laravel-notify
- **Authentication**: Laravel Sanctum

## Multi-Tenancy Design

### Database Architecture

**System Database** (`inovcom_system`):
- Stores tenant metadata (tenants table)
- Module registry and configurations
- System admin users
- Tenant-module relationships
- Subscription/licensing metadata (future)

**Tenant Databases** (`inovcom_tenant_{code}`):
- Operational data per vendor
- Tenant-specific users, roles, permissions
- Module-specific data (items, sales, etc.)
- Localized settings per vendor

### Connection Resolution

Tenant connection is resolved via middleware (`ResolveTenantContext`, `SetTenantConnection`):

1. **Query Parameter**: `?tenant=<code>`
2. **Session**: `session('tenant_code')`
3. **Subdomain**: `{code}.domain.com` (planned)
4. **Header**: `X-Tenant-Code` (for API)

The tenant connection is dynamically configured based on tenant database credentials stored in the system database.

### Routes

- **System Routes** (`/admin/*`): Admin portal for managing tenants and modules
- **Tenant Routes** (`/app/*`): Tenant portal, requires tenant context

## Module System

### Module Types

1. **Core Modules**: Always available per tenant
   - Examples: `users`, `branding`
   - Migrations run during tenant provisioning
   - Cannot be disabled

2. **Normal Modules**: Optional per tenant
   - Examples: `items`
   - Can be enabled/disabled via Admin → Activation
   - Migrations published when enabled

### Module Registry

Modules are registered in `config/modules.php`:
- Defines module metadata (label, description, route)
- Specifies lifecycle handler (optional)
- Marks core vs normal modules
- Sets default enabled state

The `modules:sync` command syncs this config to the database.

### Module Lifecycle

Modules can implement `InovCom\Kernel\Contracts\ModuleLifecycle`:
- **install()**: Called when module enabled for tenant
- **uninstall()**: Called when module disabled

### Module Protection

Routes are protected by `module:{key}` middleware:
- Checks if module is enabled for current tenant
- Returns 404 if disabled
- Hides menu items when disabled

## Authentication

### System Admin

- Guard: `auth()` (default)
- Routes: `/admin/*`
- Manages tenants, modules, system configuration

### Tenant Users

- Guard: `auth('tenant')`
- Routes: `/app/*`
- Access tenant-specific features
- RBAC via Users module

### Separate Realms

- System admin and tenant users are completely separate
- Different authentication guards
- Different user tables (system vs tenant databases)

## Data Models

### System Models

- `Tenant`: Vendor/tenant information
- `Module`: Available modules
- `TenantModule`: Tenant-module relationships

### Tenant Models

All tenant models extend `InovCom\Kernel\TenantModel`:
- Automatically uses `tenant` connection
- Scoped to tenant database
- Examples: `User`, `Role`, `Item`, `Category`

## Service Layer

### TenantManager

- Resolves tenant by code
- Configures tenant database connection
- Manages tenant context in request lifecycle

### ModuleManager

- Provides navigation links for enabled modules
- Manages module activation/deactivation
- Handles module lifecycle events

## Middleware Stack

### System Routes
1. `web` - Session, CSRF, etc.
2. `auth` - System authentication

### Tenant Routes
1. `web` - Session, CSRF, etc.
2. `tenant` - Resolves tenant context
3. `auth:tenant` - Tenant authentication
4. `module:{key}` - Module availability check

## Offline Sync (Planned)

Future implementation will include:
- PWA shell with service worker
- Local data storage (IndexedDB)
- Outbox queue for pending writes
- Sync API for push/pull
- Conflict resolution strategy

## Security

- **RBAC**: Role-based access control via Users module
- **Database Isolation**: Complete data separation per tenant
- **Connection Security**: Encrypted tenant database credentials
- **Route Protection**: Middleware-based access control
- **CSRF Protection**: Laravel's built-in CSRF tokens

## Performance Considerations

- Each tenant has isolated database (no cross-tenant queries)
- Module routes cached
- Config cached in production
- Database connection pooling per tenant

## Deployment

- **System Database**: Single PostgreSQL instance
- **Tenant Databases**: Can be on same or different servers
- **Scaling**: Horizontal scaling possible by tenant
- **Backup**: Per-tenant database backups

## See Also

- [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) - Module development guide
- [module-packages.md](./module-packages.md) - Module package details
- [SETUP.md](./SETUP.md) - Setup instructions
