<?php

namespace School\Support;

use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

/**
 * Seeds school-specific roles and attaches permissions that already exist.
 */
class SchoolRoleCatalog
{
    public static function defaultRoles(): array
    {
        return [
            [
                'name' => 'directeur',
                'description' => 'Directeur — configuration, publication et supervision',
                'permissions' => [
                    'school.view', 'school.manage',
                    'school_years.view', 'school_years.manage',
                    'school_classes.view', 'school_classes.manage',
                    'school_subjects.view', 'school_subjects.manage',
                    'school_teachers.view', 'school_teachers.manage',
                    'school_students.view', 'school_students.manage',
                    'school_enrollments.view', 'school_enrollments.manage',
                    'school_payments.view', 'school_payments.manage', 'school_payments.verify',
                    'school_id_cards.view', 'school_id_cards.manage',
                    'school_exams.view', 'school_exams.manage', 'school_exams.marks',
                    'school_grading.view', 'school_grading.manage',
                    'school_publications.view', 'school_publications.manage', 'school_publications.approve', 'school_publications.publish',
                    'school_report_cards.view', 'school_report_cards.print',
                    'school_fees.view', 'school_fees.manage',
                    'school_notifications.view', 'school_notifications.manage',
                    'school_settings.view', 'school_settings.manage',
                    'users.view', 'users.manage',
                    'configuration.view', 'configuration.edit',
                ],
            ],
            [
                'name' => 'enseignant',
                'description' => 'Enseignant — élèves, examens, notes et bulletins (lecture)',
                'permissions' => [
                    'school.view',
                    'school_years.view',
                    'school_classes.view',
                    'school_subjects.view',
                    'school_teachers.view',
                    'school_students.view',
                    'school_enrollments.view',
                    'school_exams.view', 'school_exams.manage', 'school_exams.marks',
                    'school_grading.view',
                    'school_publications.view',
                    'school_report_cards.view', 'school_report_cards.print',
                ],
            ],
            [
                'name' => 'caissier',
                'description' => 'Caissier — inscriptions (lecture), paiements, frais, cartes ID',
                'permissions' => [
                    'school.view',
                    'school_years.view',
                    'school_classes.view',
                    'school_students.view',
                    'school_enrollments.view', 'school_enrollments.manage',
                    'school_fees.view',
                    'school_payments.view', 'school_payments.manage', 'school_payments.verify',
                    'school_id_cards.view', 'school_id_cards.manage',
                    'school_notifications.view',
                ],
            ],
        ];
    }

    public static function sync(): void
    {
        foreach (self::defaultRoles() as $roleDef) {
            $role = Role::on('tenant')->firstOrCreate(
                ['name' => $roleDef['name']],
                ['description' => $roleDef['description'] ?? null]
            );

            if (! empty($roleDef['description'])) {
                $role->fill(['description' => $roleDef['description']])->save();
            }

            $permIds = Permission::on('tenant')
                ->whereIn('key', $roleDef['permissions'])
                ->pluck('id')
                ->all();

            if ($permIds !== []) {
                // Full sync so role definitions stay accurate in real usage.
                $role->permissions()->sync($permIds);
            }
        }
    }
}
