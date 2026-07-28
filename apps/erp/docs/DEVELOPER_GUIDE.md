# Developer Guide

This guide is for developers who want to build modules for the Inov-Com platform.

## Table of Contents

1. [Understanding the Architecture](#understanding-the-architecture)
2. [Module Package Structure](#module-package-structure)
3. [Creating a New Module](#creating-a-new-module)
4. [Module Components](#module-components)
5. [Best Practices](#best-practices)
6. [Testing Your Module](#testing-your-module)

## Understanding the Architecture

### Multi-Tenancy

Inov-Com uses a **multi-tenant architecture** with:

- **System Database**: Stores tenant metadata, module configurations, and system-level data
- **Tenant Databases**: Each tenant has its own PostgreSQL database for operational data

### Module Types

1. **Core Modules**: Always available per tenant (e.g., `users`, `branding`)
   - Migrations run automatically during tenant provisioning
   - Cannot be disabled per tenant

2. **Normal Modules**: Optional modules that can be enabled/disabled per tenant (e.g., `items`)
   - Migrations published when module is enabled
   - Can be toggled in Admin → Activation

### Module Lifecycle

Modules can implement `InovCom\Kernel\Contracts\ModuleLifecycle` to handle:
- `install(Tenant $tenant)`: Called when module is enabled for a tenant
- `uninstall(Tenant $tenant)`: Called when module is disabled

## Module Package Structure

A module package should follow this structure:

```
packages/inovcom/your-module/
├── composer.json
├── src/
│   ├── YourModuleServiceProvider.php
│   ├── YourModule.php (optional, if implementing ModuleLifecycle)
│   ├── Models/
│   │   └── YourModel.php
│   └── Http/
│       └── Livewire/
│           └── YourComponent.php
├── resources/
│   └── views/
│       └── livewire/
│           └── your-component.blade.php
├── database/
│   └── migrations/
│       └── YYYY_MM_DD_HHMMSS_create_your_table.php
└── routes/
    └── web.php (optional)
```

## Creating a New Module

### Step 1: Create the Package Directory

```bash
mkdir -p packages/inovcom/your-module/src
mkdir -p packages/inovcom/your-module/resources/views/livewire
mkdir -p packages/inovcom/your-module/database/migrations
```

### Step 2: Create composer.json

Create `packages/inovcom/your-module/composer.json`:

```json
{
    "name": "inovcom/your-module",
    "description": "Your module description",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.1",
        "inovcom/kernel": "@dev",
        "laravel/framework": "^10.10",
        "livewire/livewire": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "InovCom\\YourModule\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "InovCom\\YourModule\\YourModuleServiceProvider"
            ]
        }
    }
}
```

### Step 3: Create the Service Provider

Create `packages/inovcom/your-module/src/YourModuleServiceProvider.php`:

```php
<?php

namespace InovCom\YourModule;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

class YourModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-your-module');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-your-module-migrations');

        // Register Livewire components
        Livewire::component('inovcom-your-module.your-component', YourComponent::class);

        // Register tenant routes
        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/your-route', YourComponent::class)
                    ->middleware(['module:your-module'])
                    ->name('tenant.your-module.index');
            });
    }
}
```

### Step 4: Register in Main composer.json

Add to `composer.json` in the root:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/inovcom/your-module"
        }
    ],
    "require": {
        "inovcom/your-module": "@dev"
    }
}
```

Then run:
```bash
composer update inovcom/your-module
```

### Step 5: Register Service Provider

Add to `config/app.php`:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    InovCom\YourModule\YourModuleServiceProvider::class,
    // ... other providers
])->toArray(),
```

### Step 6: Add to Module Registry

Add to `config/modules.php`:

```php
'your-module' => [
    'core' => false, // Set to true if it's a core module
    'label' => 'Your Module',
    'description' => 'Description of your module',
    'route_name' => 'tenant.your-module.index',
    'lifecycle_handler' => \InovCom\YourModule\YourModule::class, // Optional
    'enabled_by_default' => false,
    'migration_tag' => 'inovcom-your-module-migrations', // For normal modules
],
```

### Step 7: Sync Modules

```bash
php artisan modules:sync
```

## Module Components

### Models

Use `InovCom\Kernel\TenantModel` as base class for tenant-scoped models:

```php
<?php

namespace InovCom\YourModule\Models;

use InovCom\Kernel\TenantModel;

class YourModel extends TenantModel
{
    protected $fillable = ['name', 'description'];
    
    // Model automatically uses 'tenant' connection
}
```

### Livewire Components

Example Livewire component:

```php
<?php

namespace InovCom\YourModule\Http\Livewire;

use InovCom\YourModule\Models\YourModel;
use Livewire\Component;

class YourComponent extends Component
{
    public $items = [];
    
    public function mount(): void
    {
        $this->items = YourModel::on('tenant')->get();
    }
    
    public function render()
    {
        return view('inovcom-your-module::livewire.your-component')
            ->layout('layouts.app', [
                'title' => 'Your Module',
                'subtitle' => '',
            ]);
    }
}
```

### Views

Views should be placed in `resources/views/livewire/` and use the namespace:

```blade
{{-- resources/views/livewire/your-component.blade.php --}}
<div class="page-body">
    <section class="card">
        <h2 class="card-title">Your Module</h2>
        <!-- Your content -->
    </section>
</div>
```

### Migrations

Migrations should be in `database/migrations/`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('your_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('your_table');
    }
};
```

**Important**: Migrations are published to `database/migrations/tenant/` and run on tenant databases.

### Module Lifecycle (Optional)

If your module needs installation/uninstallation logic:

```php
<?php

namespace InovCom\YourModule;

use App\Models\Tenant;
use InovCom\Kernel\Contracts\ModuleLifecycle;

class YourModule implements ModuleLifecycle
{
    public function install(Tenant $tenant): void
    {
        // Create default data, seed permissions, etc.
    }

    public function uninstall(Tenant $tenant): void
    {
        // Clean up data if needed
    }
}
```

## Best Practices

### 1. Naming Conventions

- **Package name**: `inovcom/your-module` (kebab-case)
- **Namespace**: `InovCom\YourModule` (PascalCase)
- **Service Provider**: `YourModuleServiceProvider`
- **Views namespace**: `inovcom-your-module` (kebab-case)

### 2. Database Connections

- Always use `->on('tenant')` when querying tenant models:
  ```php
  YourModel::on('tenant')->get();
  ```

### 3. Route Naming

- Tenant routes should follow: `tenant.{module}.{action}`
- Example: `tenant.items.index`, `tenant.items.create`

### 4. Middleware

- Always use `['web', 'tenant', 'auth:tenant']` for tenant routes
- Add `module:{module-key}` middleware to protect routes

### 5. Validation

- Use model classes in validation rules:
  ```php
  Rule::unique(YourModel::class, 'email')
  ```
  This automatically uses the correct database connection.

### 6. Error Handling

- Use `notify()->success()` for success messages
- Display validation errors in views:
  ```blade
  @error('field') <span class="field-error">{{ $message }}</span> @enderror
  ```

### 7. Form Updates

- For update forms, don't prefill password fields
- Make optional fields nullable in validation when updating
- Use PATCH-like behavior (only update provided fields)

## Testing Your Module

### Manual Testing Checklist

1. ✅ Module appears in Admin → Activation
2. ✅ Can enable/disable module for tenant
3. ✅ Routes are accessible when enabled
4. ✅ Routes return 404 when disabled
5. ✅ Migrations run when module is enabled
6. ✅ Livewire components render correctly
7. ✅ Models use correct database connection
8. ✅ Validation works with tenant connection

### Testing Commands

```bash
# Test module registration
php artisan modules:sync

# Test tenant migration
php artisan tenant:migrate <tenant-code>

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Example: Complete Module

See existing modules for reference:
- **Users Module**: `packages/inovcom/users/` - Core module with RBAC
- **Items Module**: `packages/inovcom/items/` - Normal module with CRUD
- **Branding Module**: `packages/inovcom/branding/` - Simple core module

## Getting Help

- Review existing modules for patterns
- Check `docs/module-packages.md` for detailed structure
- Review `docs/architecture.md` for system design
- Check Laravel and Livewire documentation

## Common Issues

### Module not appearing in Admin
- Run `php artisan modules:sync`
- Check `config/modules.php` entry
- Verify service provider is registered

### Routes not working
- Check middleware is correct
- Verify route is registered in service provider
- Clear route cache: `php artisan route:clear`

### Database connection errors
- Ensure using `->on('tenant')` for queries
- Check tenant database exists
- Verify tenant credentials

### Views not found
- Check view namespace matches `loadViewsFrom()`
- Verify view path is correct
- Clear view cache: `php artisan view:clear`
