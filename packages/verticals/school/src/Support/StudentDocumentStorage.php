<?php

namespace School\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use School\Models\SchoolStudentDocument;

final class StudentDocumentStorage
{
    public const DISK = 'public';

    public const DIR = 'school/student-documents';

    public static function store(UploadedFile $file, int $studentId): string
    {
        $dir = self::DIR.'/'.$studentId;
        Storage::disk(self::DISK)->makeDirectory($dir);

        return $file->store($dir, self::DISK);
    }

    public static function delete(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return;
        }

        try {
            if (Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        } catch (\Throwable) {
        }
    }

    public static function url(SchoolStudentDocument $document, ?string $tenantCode = null): ?string
    {
        $tenantCode = $tenantCode
            ?? request()->query('tenant')
            ?? request()->attributes->get('tenant')?->code
            ?? session('tenant_code');

        if ($tenantCode && Route::has('tenant.school.documents.file')) {
            return route('tenant.school.documents.file', [
                'tenant' => $tenantCode,
                'document' => $document->id,
            ]);
        }

        if ($document->file_path && Storage::disk(self::DISK)->exists($document->file_path)) {
            return Storage::disk(self::DISK)->url($document->file_path);
        }

        return null;
    }
}
