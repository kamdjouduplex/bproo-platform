<?php

namespace InovCom\Dms\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    /**
     * Store an uploaded file in the tenant-scoped local directory.
     * Returns the storage path (relative to the local disk root).
     */
    public function store(UploadedFile $file, string $tenantCode): string
    {
        $directory = "tenants/{$tenantCode}/documents";
        $safeName  = Str::uuid() . '.' . $file->getClientOriginalExtension();

        return $file->storeAs($directory, $safeName, 'local');
    }

    /**
     * Store a Livewire temporary file (already stored in livewire-tmp).
     */
    public function storeLivewireTemp(string $tempPath, string $tenantCode, string $extension): string
    {
        $directory = "tenants/{$tenantCode}/documents";
        $safeName  = Str::uuid() . '.' . $extension;
        $target    = $directory . '/' . $safeName;

        Storage::disk('local')->move($tempPath, $target);

        return $target;
    }

    /**
     * Return an absolute URL (or stream response) for a stored file.
     * For local disk, generates a signed temporary URL via the app route.
     */
    public function url(string $storagePath): string
    {
        return Storage::disk('local')->url($storagePath);
    }

    /**
     * Return the full filesystem path for streaming/download.
     */
    public function path(string $storagePath): string
    {
        return Storage::disk('local')->path($storagePath);
    }

    /**
     * Check whether the stored file still exists.
     */
    public function exists(string $storagePath): bool
    {
        return Storage::disk('local')->exists($storagePath);
    }

    /**
     * Delete a stored file.
     */
    public function delete(string $storagePath): bool
    {
        if ($this->exists($storagePath)) {
            return Storage::disk('local')->delete($storagePath);
        }
        return true;
    }

    /**
     * Total storage used by a tenant (bytes).
     */
    public function tenantUsage(string $tenantCode): int
    {
        $directory = "tenants/{$tenantCode}/documents";
        $files     = Storage::disk('local')->allFiles($directory);

        return collect($files)->sum(fn ($f) => Storage::disk('local')->size($f));
    }
}
