<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use School\Support\SchoolDocumentCatalog;
use School\Support\StudentDocumentStorage;

class SchoolStudentDocument extends TenantModel
{
    use Auditable;

    protected $table = 'school_student_documents';

    protected $fillable = [
        'student_id',
        'type',
        'title',
        'file_path',
        'original_name',
        'mime',
        'size_bytes',
        'issued_on',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'size_bytes' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'student_id');
    }

    public function typeLabel(): string
    {
        return SchoolDocumentCatalog::label((string) $this->type);
    }

    public function displayTitle(): string
    {
        return (string) ($this->title ?: $this->original_name ?: $this->typeLabel());
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes <= 0) {
            return '—';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 0).' Ko';
        }

        return number_format($bytes / (1024 * 1024), 1).' Mo';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf' || str_ends_with(strtolower((string) $this->original_name), '.pdf');
    }

    public function viewUrl(?string $tenantCode = null): ?string
    {
        return StudentDocumentStorage::url($this, $tenantCode);
    }
}
