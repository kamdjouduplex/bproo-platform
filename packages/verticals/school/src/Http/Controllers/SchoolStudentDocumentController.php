<?php

namespace School\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\SchoolStudentDocument;
use School\Support\StudentDocumentStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolStudentDocumentController
{
    use AuthorizesSchoolHttp;

    public function __invoke(int $document): StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorizeSchoolPermission('school_documents.view');

        $record = SchoolStudentDocument::query()->findOrFail($document);

        if (! $record->file_path || ! Storage::disk(StudentDocumentStorage::DISK)->exists($record->file_path)) {
            abort(404, 'Pièce introuvable.');
        }

        $raw = $record->original_name ?: basename($record->file_path);
        $name = preg_replace('/[\r\n"]+/', '', (string) $raw) ?: 'piece';

        return Storage::disk(StudentDocumentStorage::DISK)->response(
            $record->file_path,
            $name,
            [
                'Content-Disposition' => 'inline; filename="'.$name.'"',
            ]
        );
    }
}
