<?php

namespace InovCom\Dms\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Dms\Models\Document;
use InovCom\Dms\Services\StorageService;
use Livewire\Component;
use Livewire\WithFileUploads;

class DocumentUpload extends Component
{
    use WithFileUploads, AuthorizesWithTenant;

    public string  $title       = '';
    public string  $category    = 'other';
    public string  $description = '';
    public         $file        = null;

    // Pre-link to an entity (passed via query string when coming from entity panels)
    public ?string $attachableType = null;
    public ?int    $attachableId   = null;

    // Where to go after a successful upload
    public ?string $backUrl = null;

    public function mount(
        ?string $attachableType = null,
        ?int    $attachableId   = null
    ): void {
        $this->tenantAuthorize('dms.create');

        // Accept both route params and query-string params
        $this->attachableType = $attachableType ?? request()->query('attachable_type');
        $this->attachableId   = $attachableId   ?? (int) request()->query('attachable_id') ?: null;
        $this->backUrl        = request()->query('back');
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'category'    => ['required', 'in:contract,plan,permit,photo,report,invoice,quote,other'],
            'description' => ['nullable', 'string'],
            'file'        => ['required', 'max:20480'],
        ];
    }

    public function updatedFile(): void
    {
        if ($this->title === '' && $this->file) {
            $this->title = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);
        }
    }

    public function save(): void
    {
        $this->tenantAuthorize('dms.create');
        $this->validate();

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $storage    = app(StorageService::class);

        $storagePath = $storage->store($this->file, $tenantCode);

        $document = Document::create([
            'tenant_code'  => $tenantCode,
            'title'        => $this->title,
            'category'     => $this->category,
            'description'  => $this->description ?: null,
            'filename'     => $this->file->getClientOriginalName(),
            'mime_type'    => $this->file->getMimeType(),
            'file_size'    => $this->file->getSize(),
            'storage_path' => $storagePath,
            'disk'         => 'local',
            'version'      => 1,
            'uploaded_by'  => auth('tenant')->id(),
        ]);

        // Attach to entity if provided
        if ($this->attachableType && $this->attachableId) {
            $document->attachments()->create([
                'attachable_type' => $this->attachableType,
                'attachable_id'   => $this->attachableId,
            ]);
        }

        notify()->success(__('Document téléversé.'));

        // Redirect back to the entity page if we came from one, else to DMS index
        $redirect = $this->backUrl
            ?? route('tenant.dms.index', ['tenant' => $tenantCode]);

        $this->redirect($redirect, navigate: false);
    }

    public function render()
    {
        return view('inovcom-dms::livewire.documents.upload')
            ->layout('layouts.app', [
                'title'    => __('Téléverser un document'),
                'subtitle' => __('Ajouter un fichier à la bibliothèque.'),
            ]);
    }
}
