<?php

namespace School\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use School\Models\SchoolStudent;

class StudentPhotoStorage
{
    public const DISK = 'public';

    public const DIR = 'school/student-photos';

    public static function store(UploadedFile $file, ?SchoolStudent $student = null): string
    {
        Storage::disk(self::DISK)->makeDirectory(self::DIR);

        if ($student?->photo_path) {
            self::delete($student->photo_path);
        }

        return $file->store(self::DIR, self::DISK);
    }

    /**
     * Persist a cropped JPEG/PNG data URI produced by the client cropper.
     */
    public static function storeFromDataUri(string $dataUri, ?SchoolStudent $student = null): string
    {
        if (! preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $dataUri)) {
            throw new \InvalidArgumentException('Image recadrée invalide.');
        }

        $raw = substr($dataUri, strpos($dataUri, ',') + 1);
        $bin = base64_decode($raw, true);
        if ($bin === false || strlen($bin) < 32) {
            throw new \InvalidArgumentException('Impossible de décoder la photo.');
        }

        // Cap ~4 MB decoded
        if (strlen($bin) > 4 * 1024 * 1024) {
            throw new \InvalidArgumentException('Photo trop volumineuse (max 4 Mo).');
        }

        Storage::disk(self::DISK)->makeDirectory(self::DIR);

        if ($student?->photo_path) {
            self::delete($student->photo_path);
        }

        $ext = 'jpg';
        if (str_contains(strtolower($dataUri), 'image/png')) {
            $ext = 'png';
        } elseif (str_contains(strtolower($dataUri), 'image/webp')) {
            $ext = 'webp';
        }

        $path = self::DIR.'/'.uniqid('stu_', true).'.'.$ext;
        Storage::disk(self::DISK)->put($path, $bin);

        return $path;
    }

    /**
     * Preview URL for a Livewire temporary upload (works without S3).
     */
    public static function temporaryPreview($file): ?string
    {
        if (! $file) {
            return null;
        }

        try {
            if (method_exists($file, 'temporaryUrl')) {
                return $file->temporaryUrl();
            }
        } catch (\Throwable) {
            // local disk may not support temporaryUrl in some setups
        }

        try {
            $path = method_exists($file, 'getRealPath') ? $file->getRealPath() : null;
            if ($path && is_readable($path)) {
                $mime = @mime_content_type($path) ?: 'image/jpeg';

                return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }

    public static function delete(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return;
        }

        if (str_starts_with($path, '/')) {
            return;
        }

        try {
            if (Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    public static function url(?string $path, ?string $tenantCode = null, ?int $studentId = null): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        $tenantCode = $tenantCode
            ?? request()->query('tenant')
            ?? request()->attributes->get('tenant')?->code
            ?? session('tenant_code');

        if ($studentId && $tenantCode && \Illuminate\Support\Facades\Route::has('tenant.school.students.photo')) {
            return route('tenant.school.students.photo', [
                'tenant' => $tenantCode,
                'student' => $studentId,
            ]);
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->url($path);
        }

        return null;
    }

    /** Data URI for reliable print tabs (no symlink / auth needed). */
    public static function dataUri(?string $path): ?string
    {
        if (! $path || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        try {
            if (! Storage::disk(self::DISK)->exists($path)) {
                return null;
            }
            $mime = Storage::disk(self::DISK)->mimeType($path) ?: 'image/jpeg';
            $bin = Storage::disk(self::DISK)->get($path);

            return 'data:'.$mime.';base64,'.base64_encode($bin);
        } catch (\Throwable) {
            return null;
        }
    }
}
