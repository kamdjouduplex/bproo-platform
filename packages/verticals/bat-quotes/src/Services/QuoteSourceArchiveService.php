<?php

namespace InovCom\Devis\Services;

use App\Services\ModuleRegistry;
use App\Services\TenantManager;
use Illuminate\Http\UploadedFile;
use InovCom\Devis\Models\Quote;
use InovCom\Dms\Models\Document;
use InovCom\Dms\Models\DocumentAttachment;
use InovCom\Dms\Services\StorageService;

/**
 * Archives the client's original spreadsheet on a quote via the DMS module.
 */
class QuoteSourceArchiveService
{
    public function isAvailable(): bool
    {
        if (!class_exists(Document::class)) {
            return false;
        }

        $tenant = app(TenantManager::class)->tenant();

        return $tenant && app(ModuleRegistry::class)->isEnabled('dms', $tenant);
    }

    /**
     * Store the uploaded file and attach it to the quote.
     */
    public function archive(Quote $quote, UploadedFile $file): ?Document
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        if (!$tenantCode) {
            return null;
        }

        $storage = app(StorageService::class);
        $storagePath = $storage->store($file, $tenantCode);
        $originalName = $file->getClientOriginalName();

        $document = Document::on('tenant')->create([
            'tenant_code'  => $tenantCode,
            'title'        => __('Source client : :file', ['file' => $originalName]),
            'category'     => 'quote',
            'description'  => __('Fichier Excel/CSV reçu du client, importé le :date.', [
                'date' => now()->format('d/m/Y H:i'),
            ]),
            'filename'     => $originalName,
            'mime_type'    => $file->getMimeType() ?: 'application/octet-stream',
            'file_size'    => $file->getSize(),
            'storage_path' => $storagePath,
            'disk'         => 'local',
            'version'      => 1,
            'uploaded_by'  => auth('tenant')->id(),
        ]);

        DocumentAttachment::on('tenant')->create([
            'document_id'     => $document->id,
            'attachable_type' => 'quote',
            'attachable_id'   => $quote->id,
        ]);

        return $document;
    }
}
