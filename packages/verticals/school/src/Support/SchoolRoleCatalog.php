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
                'description' => 'Directeur — supervision, publication et configuration',
                'permissions' => [
                    'school.view', 'school.manage',
                    'school_years.view', 'school_years.manage',
                    'school_classes.view', 'school_classes.manage',
                    'school_subjects.view', 'school_subjects.manage',
                    'school_teachers.view', 'school_teachers.manage',
                    'school_students.view', 'school_students.manage',
                    'school_documents.view', 'school_documents.manage',
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
                    'school_attendance.view', 'school_attendance.manage',
                    'school_timetable.view', 'school_timetable.manage',
                    'school_reports.view',
                    'school_pilotage.view',
                    'users.view', 'users.manage',
                    'configuration.view', 'configuration.edit',
                ],
            ],
            [
                'name' => 'secretaire',
                'description' => 'Secrétaire — élèves, inscriptions, cartes et présences',
                'permissions' => [
                    'school.view',
                    'school_years.view',
                    'school_classes.view',
                    'school_subjects.view',
                    'school_teachers.view',
                    'school_students.view', 'school_students.manage',
                    'school_documents.view', 'school_documents.manage',
                    'school_enrollments.view', 'school_enrollments.manage',
                    'school_id_cards.view', 'school_id_cards.manage',
                    'school_attendance.view', 'school_attendance.manage',
                    'school_timetable.view', 'school_timetable.manage',
                    'school_reports.view',
                    'school_pilotage.view',
                    'school_notifications.view',
                ],
            ],
            [
                'name' => 'econome',
                'description' => 'Économe — frais, paiements, débiteurs et rapports financiers',
                'permissions' => [
                    'school.view',
                    'school_years.view',
                    'school_classes.view',
                    'school_students.view',
                    'school_enrollments.view',
                    'school_fees.view', 'school_fees.manage',
                    'school_payments.view', 'school_payments.manage', 'school_payments.verify',
                    'school_reports.view',
                    'school_pilotage.view',
                    'school_notifications.view',
                ],
            ],
            [
                'name' => 'enseignant',
                'description' => 'Enseignant — élèves, présences, notes et bulletins',
                'permissions' => [
                    'school.view',
                    'school_years.view',
                    'school_classes.view',
                    'school_subjects.view',
                    'school_teachers.view',
                    'school_students.view',
                    'school_enrollments.view',
                    'school_attendance.view', 'school_attendance.manage',
                    'school_timetable.view',
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
                    'school_reports.view',
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
                $role->permissions()->sync($permIds);
            }
        }
    }
}
