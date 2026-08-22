<?php

namespace School\Support;

use School\Models\SchoolStudent;

/**
 * Profile completeness for flexible enrollment (PRD).
 */
class StudentProfileCompletion
{
    /**
     * @return array{percent:int, missing:array<int,string>, filled:int, total:int}
     */
    public static function for(SchoolStudent $student): array
    {
        $fields = [
            'student_code' => 'Matricule',
            'nisu' => 'NISU',
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'gender' => 'Genre',
            'birth_date' => 'Date de naissance',
            'address' => 'Adresse',
            'parent_full_name' => 'Parent / tuteur',
            'parent_phone' => 'Téléphone parent',
            'emergency_contact_phone' => 'Contact d’urgence',
            'photo_path' => 'Photo',
        ];

        $missing = [];
        $filled = 0;
        foreach ($fields as $column => $label) {
            $value = $student->{$column};
            $ok = $value !== null && $value !== '';
            if ($ok) {
                $filled++;
            } else {
                $missing[] = $label;
            }
        }

        $total = count($fields);
        $percent = $total > 0 ? (int) round(($filled / $total) * 100) : 0;

        return [
            'percent' => $percent,
            'missing' => $missing,
            'filled' => $filled,
            'total' => $total,
        ];
    }
}
