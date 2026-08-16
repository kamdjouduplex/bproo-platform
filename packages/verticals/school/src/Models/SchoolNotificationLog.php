<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolNotificationLog extends TenantModel
{
    protected $table = 'school_notification_logs';

    protected $fillable = [
        'event',
        'channel',
        'status',
        'student_id',
        'recipient',
        'message',
        'error',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'student_id');
    }
}
