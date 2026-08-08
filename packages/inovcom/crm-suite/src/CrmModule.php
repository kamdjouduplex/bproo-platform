<?php

namespace InovCom\Crm;

use App\Jobs\InstallModuleJob;
use App\Models\Module;
use App\Services\ModuleRegistry;
use InovCom\Clients\ClientsModule;
use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Prospects\ProspectsModule;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

/**
 * Tenant-facing CRM module (Relation client for product apps).
 * Does not replace Control Center landlord CRM (platform_prospects).
 */
class CrmModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            // Accès global menu CRM
            ['key' => 'crm.view', 'name' => 'CRM — accès menu', 'description' => 'Voir le menu CRM (sous-menus selon droits détaillés)'],

            // Prospects
            ['key' => 'crm.prospects.view', 'name' => 'CRM — voir prospects', 'description' => 'Consulter la liste et les fiches prospects'],
            ['key' => 'crm.prospects.create', 'name' => 'CRM — créer prospects', 'description' => 'Créer de nouveaux prospects'],
            ['key' => 'crm.prospects.update', 'name' => 'CRM — modifier prospects', 'description' => 'Modifier un prospect, notes, commercial, valeur'],
            ['key' => 'crm.prospects.delete', 'name' => 'CRM — supprimer prospects', 'description' => 'Supprimer un prospect non converti'],
            ['key' => 'crm.prospects.convert', 'name' => 'CRM — convertir en client', 'description' => 'Transformer un prospect en client (visible ensuite dans Clients)'],
            ['key' => 'crm.prospects.assign', 'name' => 'CRM — affecter commercial', 'description' => 'Changer le commercial responsable d’un prospect'],

            // Opportunités (pipeline)
            ['key' => 'crm.opportunities.view', 'name' => 'CRM — voir opportunités', 'description' => 'Consulter le kanban / pipeline'],
            ['key' => 'crm.opportunities.manage', 'name' => 'CRM — gérer opportunités', 'description' => 'Avancer une opportunité, marquer gagné/perdu'],

            // Activités
            ['key' => 'crm.activities.view', 'name' => 'CRM — voir activités', 'description' => 'Consulter le journal d’activités'],
            ['key' => 'crm.activities.create', 'name' => 'CRM — créer activités', 'description' => 'Ajouter notes, appels, rendez-vous, e-mails'],
        ];
    }

    public static function dependencyKeys(): array
    {
        return ['clients', 'prospects'];
    }

    public function install(object $tenant): void
    {
        $this->ensureDependencies($tenant);
        $this->seedPermissions(self::defaultPermissions());

        if (class_exists(ProspectsModule::class)) {
            (new ProspectsModule)->install($tenant);
        }
        if (class_exists(ClientsModule::class)) {
            (new ClientsModule)->install($tenant);
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep prospects/clients data; CRM workspace is gated off via module flag.
    }

    /**
     * @param  list<array{key:string,name:string,description?:string|null}>  $permissions
     */
    private function seedPermissions(array $permissions): void
    {
        $ids = [];
        foreach ($permissions as $p) {
            $perm = Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
            // Keep labels fresh when permissions evolve
            $perm->fill([
                'name' => $p['name'],
                'description' => $p['description'] ?? null,
            ])->save();
            $ids[] = $perm->id;
        }

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin && $ids !== []) {
            $admin->permissions()->syncWithoutDetaching($ids);
        }
    }

    private function ensureDependencies(object $tenant): void
    {
        if (! class_exists(ModuleRegistry::class)) {
            return;
        }

        $registry = app(ModuleRegistry::class);
        $performedBy = auth()->id();

        foreach (self::dependencyKeys() as $key) {
            if ($registry->isEnabled($key, $tenant)) {
                continue;
            }

            try {
                if (class_exists(InstallModuleJob::class) && Module::where('key', $key)->exists()) {
                    InstallModuleJob::dispatchSync((int) $tenant->id, $key, $performedBy);
                } else {
                    // Fallback: enable pivot without full job when catalog row missing
                    $module = Module::firstOrCreate(
                        ['key' => $key],
                        [
                            'label' => config("modules.{$key}.label", $key),
                            'description' => config("modules.{$key}.description"),
                            'enabled_by_default' => false,
                        ]
                    );
                    $registry->install($key, $tenant, $performedBy);
                    $tenant->modules()->syncWithoutDetaching([
                        $module->id => ['enabled' => true],
                    ]);
                    $registry->clearCache($tenant);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
