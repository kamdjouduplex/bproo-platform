<?php

namespace School\Support;

use School\Modules\SchoolAttendanceModule;
use School\Modules\SchoolDocumentsModule;
use School\Modules\SchoolPilotageModule;
use School\Modules\SchoolReportsModule;
use School\Modules\SchoolTimetableModule;

/**
 * Idempotent bootstrap for new V2 pieces on an already-provisioned tenant.
 */
class SchoolBootstrap
{
    public static function ensure(): void
    {
        try {
            SchoolOptionCatalog::seedDefaults();
        } catch (\Throwable) {
        }

        try {
            self::syncModule(SchoolAttendanceModule::class, 'school_attendance');
            self::syncModule(SchoolTimetableModule::class, 'school_timetable');
            self::syncModule(SchoolReportsModule::class, 'school_reports');
            self::syncModule(SchoolPilotageModule::class, 'school_pilotage');
            self::syncModule(SchoolDocumentsModule::class, 'school_documents');
            SchoolRoleCatalog::sync();
        } catch (\Throwable) {
        }
    }

    /**
     * @param  class-string  $module
     */
    protected static function syncModule(string $module, string $prefix): void
    {
        if (! method_exists($module, 'defaultPermissions')) {
            return;
        }

        $dummy = new class {
            use SyncsSchoolModulePermissions;

            public function run(array $permissions, string $prefix): void
            {
                self::syncPermissions($permissions, $prefix);
            }
        };

        $dummy->run($module::defaultPermissions(), $prefix);
    }
}
