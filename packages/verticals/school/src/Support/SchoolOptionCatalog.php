<?php

namespace School\Support;

use School\Models\SchoolOption;

final class SchoolOptionCatalog
{
    public const GROUP_SECTION = 'section';
    public const GROUP_GENDER = 'gender';
    public const GROUP_ENROLLMENT_STATUS = 'enrollment_status';
    public const GROUP_PARENT_RELATIONSHIP = 'parent_relationship';
    public const GROUP_COURSE_PERIOD = 'course_period';
    public const GROUP_DOCUMENT_TYPE = 'document_type';
    public const GROUP_EXAM_KIND = 'exam_kind';
    public const GROUP_EXAM_PERIOD = 'exam_period';
    public const GROUP_EDUCATION_LEVEL = 'education_level';
    public const GROUP_DIPLOMA_KIND = 'diploma_kind';
    public const GROUP_TEACHING_SECTION = 'teaching_section';

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
            self::GROUP_PARENT_RELATIONSHIP => [
                'label' => 'Lien parent / tuteur',
                'hint' => 'Qualité du responsable légal',
                'defaults' => [
                    ['value' => 'father', 'label' => 'Père', 'sort_order' => 10],
                    ['value' => 'mother', 'label' => 'Mère', 'sort_order' => 20],
                    ['value' => 'guardian', 'label' => 'Tuteur', 'sort_order' => 30],
                    ['value' => 'other', 'label' => 'Autre', 'sort_order' => 40],
                ],
            ],
            self::GROUP_COURSE_PERIOD => [
                'label' => 'Tranches de cours',
                'hint' => 'Heures de la journée pour l’emploi du temps (1ère heure, 2e heure…).',
                'defaults' => [
                    ['value' => '07:30-08:20', 'label' => '1ère heure', 'sort_order' => 10],
                    ['value' => '08:25-09:15', 'label' => '2e heure', 'sort_order' => 20],
                    ['value' => '09:20-10:10', 'label' => '3e heure', 'sort_order' => 30],
                    ['value' => '10:30-11:20', 'label' => '4e heure', 'sort_order' => 40],
                    ['value' => '11:25-12:15', 'label' => '5e heure', 'sort_order' => 50],
                    ['value' => '13:00-13:50', 'label' => '6e heure', 'sort_order' => 60],
                    ['value' => '13:55-14:45', 'label' => '7e heure', 'sort_order' => 70],
                    ['value' => '14:50-15:40', 'label' => '8e heure', 'sort_order' => 80],
                ],
            ],
            self::GROUP_DOCUMENT_TYPE => [
                'label' => 'Types de pièces',
                'hint' => 'Documents du dossier élève. L’acte de naissance et la pièce d’identité du parent restent obligatoires.',
                'defaults' => array_map(fn ($row) => [
                    'value' => $row['value'],
                    'label' => $row['label'],
                    'sort_order' => $row['sort_order'],
                ], SchoolDocumentCatalog::defaults()),
            ],
            self::GROUP_EXAM_KIND => [
                'label' => 'Types d’évaluation',
                'hint' => 'Devoir, interrogation, épreuve de séquence, composition trimestrielle…',
                'defaults' => SchoolExamCatalog::kindDefaults(),
            ],
            self::GROUP_EXAM_PERIOD => [
                'label' => 'Périodes d’évaluation',
                'hint' => 'Séquences et trimestres. Désactivez ce que votre école n’utilise pas.',
                'defaults' => SchoolExamCatalog::periodDefaults(),
            ],
            self::GROUP_EDUCATION_LEVEL => [
                'label' => 'Niveaux d’étude (enseignants)',
                'hint' => 'Dernier niveau atteint par l’enseignant.',
                'defaults' => [
                    ['value' => 'cep', 'label' => 'CEP', 'sort_order' => 10],
                    ['value' => 'bepc', 'label' => 'BEPC', 'sort_order' => 20],
                    ['value' => 'probatoire', 'label' => 'Probatoire', 'sort_order' => 30],
                    ['value' => 'bac', 'label' => 'Baccalauréat', 'sort_order' => 40],
                    ['value' => 'licence', 'label' => 'Licence', 'sort_order' => 50],
                    ['value' => 'master', 'label' => 'Master', 'sort_order' => 60],
                    ['value' => 'doctorat', 'label' => 'Doctorat', 'sort_order' => 70],
                    ['value' => 'other', 'label' => 'Autre', 'sort_order' => 80],
                ],
            ],
            self::GROUP_DIPLOMA_KIND => [
                'label' => 'Types de diplôme (enseignants)',
                'hint' => 'Diplôme, licence, ou autre qualification.',
                'defaults' => [
                    ['value' => 'diploma', 'label' => 'Diplôme', 'sort_order' => 10],
                    ['value' => 'licence', 'label' => 'Licence', 'sort_order' => 20],
                    ['value' => 'other', 'label' => 'Autre', 'sort_order' => 30],
                ],
            ],
            self::GROUP_TEACHING_SECTION => [
                'label' => 'Sections d’enseignement',
                'hint' => 'Cycle ou filière où l’enseignant intervient.',
                'defaults' => [
                    ['value' => 'primaire', 'label' => 'Primaire', 'sort_order' => 10],
                    ['value' => 'college', 'label' => 'Collège', 'sort_order' => 20],
                    ['value' => 'lycee', 'label' => 'Lycée', 'sort_order' => 30],
                    ['value' => 'technique', 'label' => 'Technique', 'sort_order' => 40],
                    ['value' => 'commercial', 'label' => 'Commercial', 'sort_order' => 50],
                    ['value' => 'bilingue', 'label' => 'Bilingue', 'sort_order' => 60],
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
