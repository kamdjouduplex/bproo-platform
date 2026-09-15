<?php

namespace Bproo\Platform\Desktop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DesktopOutboxEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'desktop_outbox_events';

    protected $fillable = [
        'event_id',
        'type',
        'schema_version',
        'occurred_at',
        'payload',
        'status',
        'attempts',
        'sent_at',
        'last_error',
    ];

    protected $casts = [
        'schema_version' => 'integer',
        'occurred_at' => 'datetime',
        'sent_at' => 'datetime',
        'payload' => 'array',
        'attempts' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            if (empty($event->event_id)) {
                $event->event_id = (string) Str::uuid();
            }
            if (empty($event->status)) {
                $event->status = self::STATUS_PENDING;
            }
        });
    }
}
