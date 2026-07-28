<?php

namespace InovCom\Dms\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Dms\Models\Document;
use InovCom\Dms\Services\StorageService;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentsIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public string $search   = '';
    public string $category = '';
    public int    $perPage  = 15;

    public function mount(): void
    {
        $this->tenantAuthorize('dms.view');
    }

    public function updatedSearch(): void   { $this->resetPage(); }
    public function updatedCategory(): void { $this->resetPage(); }

    public function download(int $id): StreamedResponse
    {
        $this->tenantAuthorize('dms.view');
        $doc     = Document::on('tenant')->findOrFail($id);
        $storage = app(StorageService::class);

        abort_unless($storage->exists($doc->storage_path), 404);

        return response()->streamDownload(function () use ($doc, $storage) {
            echo file_get_contents($storage->path($doc->storage_path));
        }, $doc->filename, [
            'Content-Type' => $doc->mime_type ?? 'application/octet-stream',
        ]);
    }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('dms.delete');
        $doc     = Document::on('tenant')->findOrFail($id);
        $storage = app(StorageService::class);
        $storage->delete($doc->storage_path);
        $doc->delete();
        notify()->success(__('Document supprimé.'));
    }

    public function render()
    {
        $tenantCode = session('tenant_code');

        $documents = Document::on('tenant')
            ->latestVersions()
            ->where('tenant_code', $tenantCode)
            ->with(['uploader', 'versions'])
            ->when($this->search !== '', fn ($q) =>
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('filename', 'like', "%{$this->search}%")
            )
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->ordered()
            ->paginate($this->perPage);

        $storage     = app(StorageService::class);
        $usageBytes  = $storage->tenantUsage($tenantCode ?? '');

        return view('inovcom-dms::livewire.documents.index', [
            'documents'  => $documents,
            'usageBytes' => $usageBytes,
        ])->layout('layouts.app', [
            'title'    => __('Documents'),
            'subtitle' => __('Bibliothèque de documents du tenant.'),
        ]);
    }
}
