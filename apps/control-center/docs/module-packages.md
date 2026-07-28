# Inov-Com Module Packages

Modules are developed as **Composer packages** under `packages/inovcom/`. The app depends on them via path repositories.

## Current Modules

### Core Modules

- **`packages/inovcom/kernel`** – Shared contracts and base classes:
  - `InovCom\Kernel\Contracts\ModuleLifecycle` (install / uninstall)
  - `InovCom\Kernel\TenantModel` (base model for tenant-scoped data)

- **`packages/inovcom/users`** – Users module (RBAC system):
  - Models: User, Role, Permission
  - Livewire components: UserForm, UsersIndex, RoleForm, RolesIndex, PermissionsMatrix
  - Implements `ModuleLifecycle` for installation
  - Routes: `/app/users`, `/app/roles`, `/app/permissions`
  - Always available per tenant

- **`packages/inovcom/branding`** – Branding module (shop customization):
  - Livewire component: BrandingIndex
  - Manages shop name and welcome message
  - No lifecycle handler
  - Routes: `/app/branding`
  - Always available per tenant

### Normal Modules

- **`packages/inovcom/items`** – Items module (product catalog):
  - Models: Item, Category, Brand, Unit
  - Livewire components: ItemsForm, ItemsIndex
  - Implements `ModuleLifecycle`
  - Routes: `/app/items`
  - Optional per tenant

## Module Package Structure

Each module package follows this structure:

```
packages/inovcom/your-module/
├── composer.json
├── src/
│   ├── YourModuleServiceProvider.php
│   ├── YourModule.php (optional, for ModuleLifecycle)
│   ├── Models/
│   └── Http/
│       └── Livewire/
├── resources/
│   └── views/
│       └── livewire/
├── database/
│   └── migrations/
└── routes/ (optional)
```

## Adding a Module Package

For detailed instructions, see [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md).

Quick steps:

1. **Create package structure** in `packages/inovcom/your-module/`
2. **Create `composer.json`** with proper namespace and dependencies
3. **Create Service Provider** that:
   - Loads views
   - Publishes migrations (if any)
   - Registers Livewire components
   - Registers tenant routes with proper middleware
4. **Add to main `composer.json`**:
   - Add path repository
   - Require the package
5. **Register Service Provider** in `config/app.php`
6. **Add module entry** to `config/modules.php`
7. **Run commands**:
   ```bash
   composer update inovcom/your-module
   composer dump-autoload
   php artisan modules:sync
   ```

## Tenant Routes

All tenant routes should:

- Use prefix: `app`
- Use middleware: `['web', 'tenant', 'auth:tenant']`
- Add module protection: `module:{module-key}`
- Follow naming: `tenant.{module}.{action}`

Example:
```php
Route::prefix('app')
    ->middleware(['web', 'tenant', 'auth:tenant'])
    ->group(function () {
        Route::get('/your-route', YourComponent::class)
            ->middleware(['module:your-module'])
            ->name('tenant.your-module.index');
    });
```

## Migrations

### Core Module Migrations

- Core module migrations (e.g., users) are published to `database/migrations/tenant/`
- Run automatically during tenant provisioning
- Tag format: `inovcom-{module}-migrations`

### Normal Module Migrations

- Normal module migrations published when module is enabled
- Published to `database/migrations/tenant/`
- Run via `php artisan tenant:migrate <code>`

### Publishing Migrations

In your service provider:
```php
$this->publishes([
    __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
], 'inovcom-your-module-migrations');
```

## Module Configuration

Modules are registered in `config/modules.php`:

```php
'your-module' => [
    'core' => false,                    // true for core modules
    'label' => 'Your Module',           // Display name
    'description' => 'Description',     // Module description
    'route_name' => 'tenant.your-module.index',
    'lifecycle_handler' => \InovCom\YourModule\YourModule::class, // Optional
    'enabled_by_default' => false,      // Auto-enable for new tenants
    'migration_tag' => 'inovcom-your-module-migrations', // For normal modules
],
```

## Module Lifecycle

Modules can implement `InovCom\Kernel\Contracts\ModuleLifecycle`:

```php
interface ModuleLifecycle
{
    public function install(Tenant $tenant): void;
    public function uninstall(Tenant $tenant): void;
}
```

**Install**: Called when module is enabled for a tenant
- Create default data
- Seed permissions
- Setup initial configuration

**Uninstall**: Called when module is disabled
- Clean up data (optional)
- Remove permissions (optional)

## Best Practices

1. **Use TenantModel** for tenant-scoped models
2. **Always use `->on('tenant')`** when querying tenant models
3. **Follow naming conventions**: kebab-case for packages, PascalCase for namespaces
4. **Validate with model classes**: `Rule::unique(YourModel::class, 'field')`
5. **Use notify()** for success messages: `notify()->success('Message')`
6. **Display validation errors** in views with proper styling
7. **Clear caches** after changes: `php artisan cache:clear`

## Testing Your Module

1. Verify module appears in Admin → Activation
2. Test enable/disable functionality
3. Verify routes work when enabled
4. Verify routes return 404 when disabled
5. Test migrations run correctly
6. Test Livewire components render
7. Verify database connections work

## See Also

- [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) - Complete developer guide
- [architecture.md](./architecture.md) - System architecture
- Existing modules for reference examples
