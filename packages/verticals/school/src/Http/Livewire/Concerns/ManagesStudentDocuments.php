<?php

namespace School\Http\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use School\Models\SchoolStudentDocument;
use School\Support\SchoolDocumentCatalog;
use School\Support\StudentDocumentStorage;

trait ManagesStudentDocuments
{
    public string $documentType = 'birth_certificate';

    /** @var mixed */
    public $documentFile = null;

    public ?string $documentTitle = null;

    public ?string $documentIssuedOn = null;

    public ?string $documentNotes = null;

    public int $documentUploadKey = 0;

    public function saveStudentDocument(?int $forStudentId = null): void
    {
        if (! $this->authorizeSchool('school_documents.manage')) {
            return;
        }

        $studentId = $forStudentId ?? (int) ($this->studentId ?? 0);
        if ($studentId <= 0) {
            notify()->error('Choisissez un élève.');

            return;
        }

        if (! $this->documentsTableReady()) {
            notify()->error('Le module pièces n’est pas encore migré. Exécutez tenant:migrate.');

            return;
        }

        $allowed = collect(SchoolDocumentCatalog::types())->pluck('value')->all();
        $this->validate([
            'documentType' => ['required', 'string', Rule::in($allowed)],
            'documentFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
            'documentTitle' => ['nullable', 'string', 'max:160'],
            'documentIssuedOn' => ['nullable', 'date'],
            'documentNotes' => ['nullable', 'string', 'max:255'],
        ], [
            'documentFile.required' => 'Joignez le fichier (PDF ou image).',
            'documentFile.mimes' => 'Formats acceptés : PDF, JPG, PNG, WEBP.',
            'documentFile.max' => 'Fichier trop volumineux (8 Mo max).',
        ]);

        $path = StudentDocumentStorage::store($this->documentFile, $studentId);

        SchoolStudentDocument::query()->create([
            'student_id' => $studentId,
            'type' => $this->documentType,
            'title' => filled($this->documentTitle) ? trim((string) $this->documentTitle) : null,
            'file_path' => $path,
            'original_name' => $this->documentFile->getClientOriginalName(),
            'mime' => $this->documentFile->getMimeType(),
            'size_bytes' => $this->documentFile->getSize(),
            'issued_on' => $this->documentIssuedOn ?: null,
            'notes' => filled($this->documentNotes) ? trim((string) $this->documentNotes) : null,
            'uploaded_by' => Auth::guard('tenant')->id(),
        ]);

        $this->resetDocumentForm();
        notify()->success('Pièce ajoutée au dossier.');
    }

    public function deleteStudentDocument(int $id): void
    {
        if (! $this->authorizeSchool('school_documents.manage')) {
            return;
        }

        if (! $this->documentsTableReady()) {
            return;
        }

        $doc = SchoolStudentDocument::query()->findOrFail($id);
        $currentStudent = (int) ($this->studentId ?? 0);
        if ($currentStudent > 0 && (int) $doc->student_id !== $currentStudent) {
            notify()->error('Cette pièce n’appartient pas à cet élève.');

            return;
        }

        StudentDocumentStorage::delete($doc->file_path);
        $doc->delete();
        notify()->success('Pièce retirée du dossier.');
    }

    public function pickDocumentType(string $type): void
    {
        $allowed = collect(SchoolDocumentCatalog::types())->pluck('value')->all();
        if (! in_array($type, $allowed, true)) {
            return;
        }
        $this->documentType = $type;
    }

    public function resetDocumentForm(): void
    {
        $this->documentFile = null;
        $this->documentTitle = null;
        $this->documentIssuedOn = null;
        $this->documentNotes = null;
        $this->documentUploadKey++;
        $this->resetErrorBag(['documentFile', 'documentType', 'documentTitle', 'documentIssuedOn', 'documentNotes']);
    }

    protected function documentsTableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('school_student_documents');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function documentsForStudent(?int $studentId = null)
    {
        $id = $studentId ?? (int) ($this->studentId ?? 0);
        if ($id <= 0 || ! $this->documentsTableReady()) {
            return collect();
        }

        return SchoolStudentDocument::query()
            ->where('student_id', $id)
            ->orderByDesc('id')
            ->get();
    }
}
