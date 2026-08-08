<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformProspectActivity extends Model
{
    protected $fillable = [
        'platform_prospect_id',
        'user_id',
        'type',
        'subject',
        'body',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(PlatformProspect::class, 'platform_prospect_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function types(): array
    {
        return [
            'note' => 'Note',
            'call' => 'Appel',
            'email' => 'Email',
            'meeting' => 'Réunion',
            'follow_up' => 'Suivi',
            'stage_change' => 'Changement d’étape',
            'assign' => 'Affectation',
            'convert' => 'Conversion',
        ];
    }
}
