<?php

namespace InovCom\Payroll;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Payroll\Services\LeaveService;
use InovCom\Users\Models\Permission;

class PayrollModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'payroll.view', 'name' => 'Voir la paie', 'description' => 'Consulter employés, paie et bulletins'],
            ['key' => 'payroll.create', 'name' => 'Créer des fiches de paie', 'description' => 'Créer périodes de paie'],
            ['key' => 'payroll.update', 'name' => 'Modifier la paie', 'description' => 'Modifier fiches en brouillon'],
            ['key' => 'payroll.process', 'name' => 'Traiter la paie', 'description' => 'Valider et marquer comme payé'],
            ['key' => 'payroll.employees', 'name' => 'Gérer les employés', 'description' => 'CRUD employés et salaires'],
            ['key' => 'payroll.leave', 'name' => 'Gérer les congés', 'description' => 'Demandes et approbation des congés'],
            ['key' => 'payroll.adjustments', 'name' => 'Ajustements paie', 'description' => 'Jours non payés, primes et retenues manuelles'],
        ];
    }

    public function install(object $tenant): void
    {
        $existingKeys = Permission::on('tenant')->pluck('id', 'key')->keys()->flip();
        foreach (self::defaultPermissions() as $p) {
            if (!$existingKeys->has($p['key'])) {
                Permission::on('tenant')->create([
                    'key' => $p['key'],
                    'name' => $p['name'],
                    'description' => $p['description'] ?? null,
                ]);
                $existingKeys->put($p['key'], true);
            }
        }

        app(LeaveService::class)->seedDefaultTypes();
    }

    public function uninstall(object $tenant): void
    {
        //
    }
}
