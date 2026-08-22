<?php

namespace School\Support;

use Illuminate\Support\Facades\Schema;
use School\Models\SchoolOption;

final class SchoolExamCatalog
{
    /**
     * @return list<array{value:string,label:string,sort_order:int}>
     */
    public static function kindDefaults(): array
    {
        return [
            ['value' => 'devoir', 'label' => 'Devoir', 'sort_order' => 10],
            ['value' => 'interrogation', 'label' => 'Interrogation', 'sort_order' => 20],
            ['value' => 'sequence', 'label' => 'Épreuve de séquence', 'sort_order' => 30],
            ['value' => 'term', 'label' => 'Composition trimestrielle', 'sort_order' => 40],
            ['value' => 'mock', 'label' => 'Examen blanc', 'sort_order' => 50],
            ['value' => 'makeup', 'label' => 'Rattrapage', 'sort_order' => 60],
            ['value' => 'other', 'label' => 'Autre', 'sort_order' => 70],
        ];
    }

    /**
     * @return list<array{value:string,label:string,sort_order:int}>
     */
    public static function periodDefaults(): array
    {
        return [
            ['value' => 'seq_1', 'label' => 'Séquence 1', 'sort_order' => 10],
            ['value' => 'seq_2', 'label' => 'Séquence 2', 'sort_order' => 20],
            ['value' => 'seq_3', 'label' => 'Séquence 3', 'sort_order' => 30],
            ['value' => 'seq_4', 'label' => 'Séquence 4', 'sort_order' => 40],
            ['value' => 'seq_5', 'label' => 'Séquence 5', 'sort_order' => 50],
            ['value' => 'seq_6', 'label' => 'Séquence 6', 'sort_order' => 60],
            ['value' => 'term_1', 'label' => 'Trimestre 1', 'sort_order' => 70],
            ['value' => 'term_2', 'label' => 'Trimestre 2', 'sort_order' => 80],
            ['value' => 'term_3', 'label' => 'Trimestre 3', 'sort_order' => 90],
            ['value' => 'annual', 'label' => 'Annuel', 'sort_order' => 100],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function kinds(): array
    {
        return self::fromOptions(SchoolOptionCatalog::GROUP_EXAM_KIND, self::kindDefaults());
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function periods(): array
    {
        return self::fromOptions(SchoolOptionCatalog::GROUP_EXAM_PERIOD, self::periodDefaults());
    }

    /**
     * @return list<string>
     */
    public static function kindValues(): array
    {
        return array_column(self::kinds(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function periodValues(): array
    {
        return array_column(self::periods(), 'value');
    }

    public static function kindLabel(?string $value): string
    {
        return self::labelOf(self::kinds(), (string) $value);
    }

    public static function periodLabel(?string $value): string
    {
        return self::labelOf(self::periods(), (string) $value);
    }

    public static function suggestTitle(?string $kind, ?string $period): string
    {
        $kindLabel = self::kindLabel($kind);
        $periodLabel = self::periodLabel($period);
        $parts = array_values(array_filter([$kindLabel, $periodLabel]));

        return implode(' — ', $parts);
    }

    /**
     * @param  list<array{value:string,label:string,sort_order?:int}>  $defaults
     * @return list<array{value:string,label:string}>
     */
    protected static function fromOptions(string $group, array $defaults): array
    {
        try {
            if (Schema::connection('tenant')->hasTable('school_options')) {
                $rows = SchoolOption::forGroup($group);
                if ($rows->isNotEmpty()) {
                    return $rows->map(fn ($row) => [
                        'value' => (string) $row->value,
                        'label' => (string) $row->label,
                    ])->values()->all();
                }
            }
        } catch (\Throwable) {
        }

        return array_map(fn ($row) => [
            'value' => $row['value'],
            'label' => $row['label'],
        ], $defaults);
    }

    /**
     * @param  list<array{value:string,label:string}>  $rows
     */
    protected static function labelOf(array $rows, string $value): string
    {
        if ($value === '') {
            return '';
        }
        foreach ($rows as $row) {
            if ($row['value'] === $value) {
                return $row['label'];
            }
        }

        return $value;
    }
}
