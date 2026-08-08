# Setup Guide

This guide will help you clone and set up the Inov-Com project for development.

## Prerequisites

Before you begin, ensure you have the following installed on your system:

- **PHP 8.1 or higher** with extensions:
  - BCMath
  - Ctype
  - Fileinfo
  - JSON
  - Mbstring
  - OpenSSL
  - PDO
  - Tokenizer
  - XML
- **Composer** (latest version)
- **PostgreSQL 12+** (for database)
- **Node.js 18+** and **npm** (for frontend assets)
- **Git**

## Step 1: Clone the Repository

```bash
git clone <repository-url> revo-com
cd revo-com
```

## Step 2: Install PHP Dependencies

```bash
composer install
```

This will install all PHP dependencies including the module packages located in `packages/inovcom/`.

## Step 3: Environment Configuration

1. Copy the environment file:
```bash
cp .env.example .env
```

2. Generate the application key:
```bash
php artisan key:generate
```

3. Edit `.env` and configure the following:

```env
APP_NAME="Inov-Com"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration (System Database)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=inovcom_system
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Session Configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Cache Configuration
CACHE_DRIVER=file

# Queue Configuration
QUEUE_CONNECTION=sync
```

**Important:** The system database stores tenant metadata. Each tenant will have its own separate database created automatically.

## Step 4: Create the System Database

Create a PostgreSQL database for the system:

```sql
CREATE DATABASE inovcom_system;
```

Or using psql command line:
```bash
createdb inovcom_system
```

## Step 5: Run System Migrations

Run migrations for the system database:

```bash
php artisan migrate
```

This creates the core system tables (tenants, modules, tenant_modules, etc.).

## Step 6: Sync Modules

Register all modules from `config/modules.php`:

```bash
php artisan modules:sync
```

This command:
- Reads module definitions from `config/modules.php`
- Creates entries in the `modules` table
- Sets up module relationships

## Step 7: Install Frontend Dependencies

```bash
npm install
```

## Step 8: Build Frontend Assets

For development:
```bash
npm run dev
```

For production:
```bash
npm run build
```

## Step 9: Create a Test Tenant

You can create a tenant using the admin interface or via artisan command:

```bash
php artisan tinker
```

Then in tinker:
```php
$tenant = App\Models\Tenant::create([
    'name' => 'Test Shop',
    'code' => 'test-shop',
    'db_name' => 'inovcom_tenant_test',
    'db_host' => '127.0.0.1',
    'db_port' => 5432,
    'db_username' => 'your_db_user',
    'db_password' => 'your_db_password',
    'is_active' => true,
]);
```

Then create the tenant database and run migrations:

```bash
# Create the tenant database
createdb inovcom_tenant_test

# Run tenant migrations
php artisan tenant:migrate test-shop

# Seed tenant with initial data (if needed)
php artisan tenant:seed test-shop

# Install users module for the tenant
php artisan users:install-tenant test-shop
```

## Step 10: Start the Development Server

```bash
php artisan serve
```

The application will be available at `http://localhost:8000`.

## Step 11: Access the Application

### System Admin Portal
- URL: `http://localhost:8000/admin`
- Default route: System dashboard for managing tenants and modules

### Tenant Portal
- URL: `http://localhost:8000/app?tenant=<tenant-code>`
- Example: `http://localhost:8000/app?tenant=test-shop`
- Requires tenant authentication

## Common Setup Issues

### Issue: Composer autoload errors
**Solution:**
```bash
composer dump-autoload
```

### Issue: Module not found after adding new module
**Solution:**
```bash
composer dump-autoload
php artisan modules:sync
```

### Issue: Tenant database connection errors
**Solution:**
- Verify tenant database exists
- Check tenant credentials in `tenants` table
- Ensure PostgreSQL user has permissions

### Issue: Livewire components not loading
**Solution:**
```bash
php artisan livewire:publish --config
npm run dev
```

### Issue: Permission denied on storage/logs
**Solution:**
```bash
chmod -R 775 storage bootstrap/cache
```

## Development Workflow

1. **Make code changes** in your IDE
2. **Run tests** (when available): `php artisan test`
3. **Clear caches** if needed:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```
4. **Watch for changes** in frontend:
   ```bash
   npm run dev
   ```

## Next Steps

- Read [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) to learn how to build modules
- Review [architecture.md](./architecture.md) to understand the system design
- Check [module-packages.md](./module-packages.md) for module package structure
