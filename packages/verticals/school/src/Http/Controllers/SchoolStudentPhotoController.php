<?php

namespace School\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\SchoolStudent;
use School\Support\StudentPhotoStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolStudentPhotoController
{
    use AuthorizesSchoolHttp;

    public function __invoke(int $student): StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorizeSchoolPermission('school_students.view');

        $record = SchoolStudent::query()->findOrFail($student);

        if (! $record->photo_path || ! Storage::disk(StudentPhotoStorage::DISK)->exists($record->photo_path)) {
            abort(404, 'Photo introuvable.');
        }

        return Storage::disk(StudentPhotoStorage::DISK)->response(
            $record->photo_path,
            basename($record->photo_path),
            ['Content-Disposition' => 'inline; filename="'.basename($record->photo_path).'"']
        );
    }
}
