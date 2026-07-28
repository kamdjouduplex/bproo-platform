<?php

namespace InovCom\Dms\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class Document extends TenantModel
{
    use Auditable;

    protected $table = 'documents';

    protected $fillable = [
        'tenant_code', 'title', 'category', 'description',
        'filename', 'mime_type', 'file_size', 'storage_path', 'disk',
        'version', 'parent_id', 'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'version'   => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────
    public function uploader()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'uploaded_by');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function versions()
    {
        return $this->hasMany(self::class, 'parent_id')->orderByDesc('version');
    }

    public function attachments()
    {
        return $this->hasMany(DocumentAttachment::class, 'document_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeLatestVersions($query)
    {
        // Only return root documents (not old versions)
        return $query->whereNull('parent_id');
    }

    // ── Business logic ────────────────────────────────────────────────

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function humanFileSize(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'contract' => 'Contrat',
            'plan'     => 'Plan',
            'permit'   => 'Permis',
            'photo'    => 'Photo',
            'report'   => 'Rapport',
            'invoice'  => 'Facture',
            'quote'    => 'Devis',
            default    => 'Autre',
        };
    }
}
