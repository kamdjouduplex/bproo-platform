<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleEvent extends Model
{
    protected $fillable = [
        'tenant_id',
        'module_key',
        'action',
        'performed_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
