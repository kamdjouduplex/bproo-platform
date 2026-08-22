<?php

namespace School\Support;

use Illuminate\Support\Facades\Schema;
use School\Models\SchoolOption;

final class SchoolDocumentCatalog
{
    /**
     * @return list<array{value:string,label:string,required:bool,sort_order:int}>
     */
    public static function defaults(): array
    {
        return [
            ['value' => 'birth_certificate', 'label' => 'Copie d’acte de naissance', 'required' => true, 'sort_order' => 10],
            ['value' => 'parent_id', 'label' => 'Pièce d’identité du parent / tuteur', 'required' => true, 'sort_order' => 20],
            ['value' => 'student_id', 'label' => 'Pièce d’identité de l’élève', 'required' => false, 'sort_order' => 30],
            ['value' => 'photo', 'label' => 'Photos d’identité', 'required' => false, 'sort_order' => 40],
            ['value' => 'vaccination', 'label' => 'Carnet de vaccination', 'required' => false, 'sort_order' => 50],
            ['value' => 'previous_report', 'label' => 'Bulletin de l’école précédente', 'required' => false, 'sort_order' => 60],
            ['value' => 'transfer', 'label' => 'Certificat de transfert / radiation', 'required' => false, 'sort_order' => 70],
            ['value' => 'residence', 'label' => 'Justificatif de domicile', 'required' => false, 'sort_order' => 80],
            ['value' => 'medical', 'label' => 'Certificat médical', 'required' => false, 'sort_order' => 90],
            ['value' => 'other', 'label' => 'Autre pièce', 'required' => false, 'sort_order' => 100],
        ];
    }

    /**
     * @return list<array{value:string,label:string,required:bool}>
     */
    public static function types(): array
    {
        $required = self::requiredValues();
        try {
            if (Schema::connection('tenant')->hasTable('school_options')) {
                $rows = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_DOCUMENT_TYPE);
                if ($rows->isNotEmpty()) {
                    return $rows->map(fn ($row) => [
                        'value' => (string) $row->value,
                        'label' => (string) $row->label,
                        'required' => in_array((string) $row->value, $required, true),
                    ])->values()->all();
                }
            }
        } catch (\Throwable) {
        }

        return array_map(fn ($row) => [
            'value' => $row['value'],
            'label' => $row['label'],
            'required' => $row['required'],
        ], self::defaults());
    }

    /**
     * @return list<string>
     */
    public static function requiredValues(): array
    {
        return ['birth_certificate', 'parent_id'];
    }

    public static function label(string $value): string
    {
        foreach (self::types() as $type) {
            if ($type['value'] === $value) {
                return $type['label'];
            }
        }

        return $value !== '' ? $value : 'Pièce';
    }

    /**
     * @param  iterable<int, object>  $documents
     * @return list<array{value:string,label:string,required:bool,has:bool}>
     */
    public static function checklist(iterable $documents): array
    {
        $have = collect($documents)->pluck('type')->unique()->all();
        $items = [];
        foreach (self::types() as $type) {
            $items[] = [
                'value' => $type['value'],
                'label' => $type['label'],
                'required' => $type['required'],
                'has' => in_array($type['value'], $have, true),
            ];
        }

        return $items;
    }

    /**
     * @param  iterable<int, object>  $documents
     * @return array{have:int,required:int,complete:bool,missing:list<string>,totalFiles:int}
     */
    public static function coverage(iterable $documents): array
    {
        $collection = collect($documents);
        $haveTypes = $collection->pluck('type')->unique();
        $required = self::requiredValues();
        $got = collect($required)->filter(fn ($value) => $haveTypes->contains($value))->count();
        $missing = collect($required)
            ->reject(fn ($value) => $haveTypes->contains($value))
            ->map(fn ($value) => self::label($value))
            ->values()
            ->all();

        return [
            'have' => $got,
            'required' => count($required),
            'complete' => $required !== [] && $got === count($required),
            'missing' => $missing,
            'totalFiles' => $collection->count(),
        ];
    }
}
