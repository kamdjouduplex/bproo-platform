<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class ClientActivity extends TenantModel
{
    protected $table = 'client_activities';

    protected $fillable = [
        'client_id', 'user_id', 'type', 'subject', 'notes', 'activity_at',
    ];

    protected $casts = [
        'activity_at' => 'datetime',
    ];

    /** Log a new activity in one call. */
    public static function log(int $clientId, string $type, string $subject, ?string $notes = null, ?int $userId = null): self
    {
        return static::create([
            'client_id'   => $clientId,
            'user_id'     => $userId ?? (auth('tenant')->id() ?? auth()->id()),
            'type'        => $type,
            'subject'     => $subject,
            'notes'       => $notes,
            'activity_at' => now(),
        ]);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'user_id');
    }
}
