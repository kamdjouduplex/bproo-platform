<?php

namespace School\Support;

use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use School\Models\AcademicYear;
use School\Models\SchoolStudent;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SchoolMinistryExport
{
    /**
     * @param  \Illuminate\Support\Collection<int, SchoolStudent>  $students
     */
    public static function download($students, ?int $yearId = null): StreamedResponse
    {
        $schoolCode = SchoolSettings::get(SchoolSettings::KEY_MINISTRY_SCHOOL_CODE);
        $yearName = $yearId
            ? (AcademicYear::query()->find($yearId)?->name ?? '')
            : (AcademicYear::query()->where('is_active', true)->value('name') ?? '');

        $filename = 'eleves-ministere-'.now()->format('Ymd-His').'.xlsx';

        if (class_exists(Spreadsheet::class)) {
            return response()->streamDownload(function () use ($students, $schoolCode, $yearName) {
                $spreadsheet = self::spreadsheet($students, $schoolCode, $yearName);
                (new Xlsx($spreadsheet))->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response()->streamDownload(function () use ($students, $schoolCode, $yearName) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Code établissement (ministère)', $schoolCode], ';');
            fputcsv($handle, ['Année', $yearName], ';');
            fputcsv($handle, ['Exporté le', now()->format('d/m/Y H:i')], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, self::headers(), ';');
            foreach (self::rows($students) as $row) {
                fputcsv($handle, $row, ';');
            }
            fclose($handle);
        }, str_replace('.xlsx', '.csv', $filename), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SchoolStudent>  $students
     */
    protected static function spreadsheet($students, string $schoolCode, string $yearName): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Élèves');

        $sheet->setCellValue('A1', 'Code établissement (ministère)');
        $sheet->setCellValue('B1', $schoolCode !== '' ? $schoolCode : '— à renseigner');
        $sheet->setCellValue('A2', 'Année académique');
        $sheet->setCellValue('B2', $yearName !== '' ? $yearName : '—');
        $sheet->setCellValue('A3', 'Exporté le');
        $sheet->setCellValue('B3', now()->format('d/m/Y H:i'));
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);

        $headers = self::headers();
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 5], $header);
        }
        $sheet->getStyle('A5:N5')->getFont()->setBold(true);

        $rowNum = 6;
        foreach (self::rows($students) as $row) {
            foreach ($row as $col => $value) {
                $sheet->setCellValue([$col + 1, $rowNum], $value);
            }
            $rowNum++;
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'Code établissement',
            'NISU',
            'Matricule interne',
            'Nom',
            'Prénom',
            'Genre',
            'Date de naissance',
            'Lieu de naissance',
            'Classe',
            'Section',
            'Parent / tuteur',
            'Téléphone parent',
            'Adresse',
            'Statut',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SchoolStudent>  $students
     * @return list<list<string>>
     */
    public static function rows($students): array
    {
        $schoolCode = SchoolSettings::get(SchoolSettings::KEY_MINISTRY_SCHOOL_CODE);

        return $students->map(function (SchoolStudent $s) use ($schoolCode) {
            $enrollment = $s->currentEnrollment;

            return [
                $schoolCode,
                (string) ($s->nisu ?? ''),
                (string) ($s->student_code ?? ''),
                (string) ($s->last_name ?? ''),
                (string) ($s->first_name ?? ''),
                (string) ($s->gender ?? ''),
                (string) ($s->birth_date?->format('d/m/Y') ?? ''),
                (string) ($s->birth_place ?? ''),
                (string) ($enrollment?->schoolClass?->name ?? ''),
                (string) ($enrollment?->section ?: ($enrollment?->schoolClass?->section ?? '')),
                (string) ($s->parent_full_name ?? ''),
                (string) ($s->parent_phone ?? ''),
                (string) ($s->address ?? ''),
                $s->is_active ? 'Actif' : 'Inactif',
            ];
        })->values()->all();
    }

    public static function nisuColumnReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasColumn('school_students', 'nisu');
        } catch (\Throwable) {
            return false;
        }
    }
}
