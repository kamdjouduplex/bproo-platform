<?php

namespace Bproo\Platform\Desktop\Models;

use Illuminate\Database\Eloquent\Model;

class DesktopInboxEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAILED = 'failed';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'cloud_id',
        'event_id',
        'type',
        'schema_version',
        'occurred_at',
        'payload',
        'source_install_uuid',
        'status',
        'applied_at',
        'last_error',
    ];

    protected $casts = [
        'cloud_id' => 'integer',
        'schema_version' => 'integer',
        'occurred_at' => 'datetime',
        'payload' => 'array',
        'applied_at' => 'datetime',
    ];
}
