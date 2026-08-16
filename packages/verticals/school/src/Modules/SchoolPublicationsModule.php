<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Models\SchoolPublicationRule;
use School\Support\SyncsSchoolModulePermissions;

class SchoolPublicationsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_publications.view', 'name' => 'Voir les publications', 'description' => 'Consulter publications et règles'],
            ['key' => 'school_publications.manage', 'name' => 'Gérer les publications', 'description' => 'Préparer et soumettre les résultats'],
            ['key' => 'school_publications.approve', 'name' => 'Approuver la publication', 'description' => 'Validation directeur'],
            ['key' => 'school_publications.publish', 'name' => 'Publier les résultats', 'description' => 'Rendre les résultats publics'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_publications');

        if (! SchoolPublicationRule::query()->exists()) {
            SchoolPublicationRule::query()->create([
                'name' => 'Règle standard',
                'require_fees_paid' => false,
                'min_fees_amount' => null,
                'require_validated_marks' => true,
                'require_director_approval' => true,
                'is_active' => true,
                'description' => 'Notes validées + approbation directeur avant publication.',
            ]);
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
