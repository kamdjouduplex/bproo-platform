<?php

namespace InovCom\Attendance;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class AttendanceModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'attendance.punch', 'name' => 'Pointer arrivée / départ', 'description' => 'Enregistrer son arrivée et son départ'],
            ['key' => 'attendance.view', 'name' => 'Voir sa présence', 'description' => 'Consulter son historique de pointage'],
            ['key' => 'attendance.view_all', 'name' => 'Voir toutes les présences', 'description' => 'Consulter les pointages de tous les employés'],
            ['key' => 'attendance.sheet', 'name' => 'Fiches de présence', 'description' => 'Générer et imprimer les fiches de présence (individuelles ou équipe)'],
            ['key' => 'attendance.settings', 'name' => 'Configurer présence (Wi‑Fi / kiosk)', 'description' => 'Configurer le réseau autorisé, le code kiosk et le pointage public'],
        ];
    }

    public function install(object $tenant): void
    {
        $this->syncPermissions();
    }

    public function uninstall(object $tenant): void
    {
        //
    }

    public static function syncPermissions(): void
    {
        foreach (self::defaultPermissions() as $p) {
            Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
        }
    }
}
