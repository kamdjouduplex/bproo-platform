<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Tenant;
use App\Services\ModuleManager;
use App\Services\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class ModulesDiagnose extends Command
{
    protected $signature = 'modules:diagnose
                            {--tenant= : Tenant code to check (optional, otherwise all tenants)}';

    protected $description = 'Diagnose modules and tenant_modules in central DB; show what sidebar would display per tenant.';

    public function handle(ModuleRegistry $registry, ModuleManager $moduleManager): int
    {
        $tenantCode = $this->option('tenant');

        $this->info('=== Base de données CENTRALE (connexion par défaut) ===');
        $this->newLine();

        // 1. List modules table (central)
        $modules = Module::orderBy('key')->get(['id', 'key', 'label']);
        if ($modules->isEmpty()) {
            $this->warn('Table modules vide. Exécutez: php artisan modules:sync');
        } else {
            $this->table(['id', 'key', 'label'], $modules->map(fn ($m) => [$m->id, $m->key, $m->label])->toArray());
        }
        $this->newLine();

        // 2. Raw tenant_modules (central)
        $pivotRows = DB::table('tenant_modules')->orderBy('tenant_id')->orderBy('module_id')->get();
        if ($pivotRows->isEmpty()) {
            $this->warn('Table tenant_modules vide. Aucun module attaché à aucun vendeur.');
        } else {
            $moduleKeys = Module::pluck('key', 'id');
            $tenantCodes = Tenant::pluck('code', 'id');
            $rows = $pivotRows->map(fn ($r) => [
                $r->tenant_id,
                $tenantCodes[$r->tenant_id] ?? '?',
                $r->module_id,
                $moduleKeys[$r->module_id] ?? '?',
                $r->enabled ? 'true' : 'false',
            ])->toArray();
            $this->table(['tenant_id', 'tenant_code', 'module_id', 'module_key', 'enabled'], $rows);
        }
        $this->newLine();

        // 3. Tenants
        $tenants = $tenantCode
            ? Tenant::where('code', $tenantCode)->get()
            : Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->warn($tenantCode ? "Aucun tenant avec le code: {$tenantCode}" : 'Aucun tenant.');
            return self::FAILURE;
        }

        $this->info('=== Par vendeur: modules activés (sidebar) ===');
        $this->newLine();

        foreach ($tenants as $tenant) {
            $this->line("<fg=cyan>Vendeur: {$tenant->name} (id={$tenant->id}, code={$tenant->code})</>");

            $enabledFromDb = $registry->getEnabledModulesFromDb($tenant);
            $this->line('  getEnabledModulesFromDb(tenant): ' . (count($enabledFromDb) ? implode(', ', $enabledFromDb) : '(vide)'));

            $navLinks = $moduleManager->navLinksForTenant($tenant, null);
            $linkKeys = array_column($navLinks, 'key');
            $this->line('  navLinksForTenant (clés): ' . (count($linkKeys) ? implode(', ', $linkKeys) : '(vide)'));

            $missingRoutes = [];
            foreach ($enabledFromDb as $moduleKey) {
                $routeName = config("modules.{$moduleKey}.route_name");
                if ($routeName && ! Route::has($routeName)) {
                    $missingRoutes[] = "{$moduleKey} → {$routeName}";
                }
            }
            if ($missingRoutes !== []) {
                $this->warn('  Routes manquantes (module activé mais invisible au menu):');
                foreach ($missingRoutes as $missing) {
                    $this->line("    - {$missing}");
                }
                $this->line('  → Vérifier le ServiceProvider du package (autoload / composer update).');
            }

            // Test tenant DB connection
            try {
                config(['database.connections.tenant' => $tenant->databaseConfig()]);
                DB::purge('tenant');
                DB::reconnect('tenant');
                DB::connection('tenant')->getPdo();
                $this->line('  Connexion BDD tenant: OK');
            } catch (\Throwable $e) {
                $this->line('  Connexion BDD tenant: <fg=red>ERREUR</> ' . $e->getMessage());
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
