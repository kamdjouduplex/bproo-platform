<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolNotificationSetting extends TenantModel
{
    protected $table = 'school_notification_settings';

    protected $fillable = ['key', 'value'];
}
