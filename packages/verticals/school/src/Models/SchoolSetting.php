<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolSetting extends TenantModel
{
    protected $table = 'school_settings';

    protected $fillable = ['key', 'value'];
}
