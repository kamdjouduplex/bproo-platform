<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PressingNotification extends TenantModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'pressing_notifications';

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'order_id',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $notification) {
            if (empty($notification->id)) {
                $notification->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
