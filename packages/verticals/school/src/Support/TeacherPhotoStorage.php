<?php

namespace School\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use School\Models\SchoolTeacher;

class TeacherPhotoStorage
{
    public const DISK = 'public';

    public const DIR = 'school/teacher-photos';

    public static function store(UploadedFile $file, ?SchoolTeacher $teacher = null): string
    {
        Storage::disk(self::DISK)->makeDirectory(self::DIR);

        if ($teacher?->photo_path) {
            self::delete($teacher->photo_path);
        }

        return $file->store(self::DIR, self::DISK);
    }

    public static function temporaryPreview($file): ?string
    {
        return StudentPhotoStorage::temporaryPreview($file);
    }

    public static function delete(?string $path): void
    {
        StudentPhotoStorage::delete($path);
    }

    public static function url(?string $path, ?string $tenantCode = null, ?int $teacherId = null): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return $path;
        }

        $tenantCode = $tenantCode
            ?? request()->query('tenant')
            ?? request()->attributes->get('tenant')?->code
            ?? session('tenant_code');

        if ($teacherId && $tenantCode && \Illuminate\Support\Facades\Route::has('tenant.school.teachers.photo')) {
            return route('tenant.school.teachers.photo', [
                'tenant' => $tenantCode,
                'teacher' => $teacherId,
            ]);
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->url($path);
        }

        return null;
    }
}
