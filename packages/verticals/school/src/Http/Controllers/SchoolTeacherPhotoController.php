<?php

namespace School\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\SchoolTeacher;
use School\Support\TeacherPhotoStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolTeacherPhotoController
{
    use AuthorizesSchoolHttp;

    public function __invoke(int $teacher): StreamedResponse|\Illuminate\Http\Response
    {
        $record = SchoolTeacher::query()->findOrFail($teacher);
        $user = auth('tenant')->user();
        $isOwn = $record->isOwnedBy($user);

        if (! $isOwn) {
            $this->authorizeSchoolPermission('school_teachers.view');
        } elseif (! $user) {
            abort(403, 'Permission refusée.');
        }

        if (! $record->photo_path || ! Storage::disk(TeacherPhotoStorage::DISK)->exists($record->photo_path)) {
            abort(404, 'Photo introuvable.');
        }

        return Storage::disk(TeacherPhotoStorage::DISK)->response(
            $record->photo_path,
            basename($record->photo_path),
            ['Content-Disposition' => 'inline; filename="'.basename($record->photo_path).'"']
        );
    }
}
