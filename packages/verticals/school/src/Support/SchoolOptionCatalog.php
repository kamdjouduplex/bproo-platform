<?php

namespace School\Support;

use School\Models\SchoolOption;

final class SchoolOptionCatalog
{
    public const GROUP_SECTION = 'section';
    public const GROUP_GENDER = 'gender';
    public const GROUP_ENROLLMENT_STATUS = 'enrollment_status';

    /**
     * @return array<string, array{label: string, hint: string, defaults: list<array{value: string, label: string, sort_order: int}>}>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_SECTION => [
                'label' => 'Sections',
                'hint' => 'Subdivisions de classe (A, B, C…)',
                'defaults' => [
                    ['value' => 'A', 'label' => 'A', 'sort_order' => 10],
                    ['value' => 'B', 'label' => 'B', 'sort_order' => 20],
                    ['value' => 'C', 'label' => 'C', 'sort_order' => 30],
                ],
            ],
            self::GROUP_GENDER => [
                'label' => 'Genres',
                'hint' => 'Valeurs affichées pour le genre élève',
                'defaults' => [
                    ['value' => 'M', 'label' => 'Masculin', 'sort_order' => 10],
                    ['value' => 'F', 'label' => 'Féminin', 'sort_order' => 20],
                ],
            ],
            self::GROUP_ENROLLMENT_STATUS => [
                'label' => 'Statuts d’inscription',
                'hint' => 'Statuts utilisés à l’inscription',
                'defaults' => [
                    ['value' => 'enrolled', 'label' => 'Inscrit', 'sort_order' => 10],
                    ['value' => 'transferred', 'label' => 'Transféré', 'sort_order' => 20],
                    ['value' => 'graduated', 'label' => 'Diplômé', 'sort_order' => 30],
                    ['value' => 'inactive', 'label' => 'Inactif', 'sort_order' => 40],
                ],
            ],
        ];
    }

    public static function seedDefaults(): void
    {
        foreach (self::groups() as $groupKey => $meta) {
            foreach ($meta['defaults'] as $row) {
                SchoolOption::query()->firstOrCreate(
                    [
                        'group_key' => $groupKey,
                        'value' => $row['value'],
                    ],
                    [
                        'label' => $row['label'],
                        'sort_order' => $row['sort_order'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
