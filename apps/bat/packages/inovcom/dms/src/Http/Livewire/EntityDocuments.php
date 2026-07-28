<?php

namespace InovCom\Dms\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Dms\Models\DocumentAttachment;
use Livewire\Component;

/**
 * Embeddable read-only document panel for any entity.
 * Upload is delegated to the standalone DocumentUpload page.
 *
 * Usage:
 *   <livewire:inovcom-dms.entity-documents
 *       attachable-type="project"
 *       :attachable-id="$projectId"
 *   />
 */
class EntityDocuments extends Component
{
    use AuthorizesWithTenant;

    public string $attachableType = '';
    public int    $attachableId   = 0;

    public function mount(string $attachableType, int $attachableId): void
    {
        $this->tenantAuthorize('dms.view');

        $this->attachableType = $attachableType;
        $this->attachableId   = $attachableId;
    }

    public function detach(int $documentId): void
    {
        DocumentAttachment::on('tenant')
            ->where('document_id', $documentId)
            ->where('attachable_type', $this->attachableType)
            ->where('attachable_id', $this->attachableId)
            ->delete();
    }

    public function download(int $documentId): void
    {
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        $this->redirect(
            route('tenant.dms.download', ['tenant' => $tenantCode, 'document' => $documentId])
        );
    }

    public function render()
    {
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        $attachments = DocumentAttachment::on('tenant')
            ->with('document')
            ->where('attachable_type', $this->attachableType)
            ->where('attachable_id', $this->attachableId)
            ->get();

        $uploadUrl = route('tenant.dms.upload', [
            'tenant'          => $tenantCode,
            'attachable_type' => $this->attachableType,
            'attachable_id'   => $this->attachableId,
            'back'            => url()->full(),
        ]);

        return view('inovcom-dms::livewire.entity-documents', [
            'attachments' => $attachments,
            'uploadUrl'   => $uploadUrl,
        ]);
    }
}
