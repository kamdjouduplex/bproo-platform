<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;

class ReturnComment extends TenantModel
{
    protected $table = 'return_comments';

    protected $fillable = [
        'return_id',
        'body',
        'author_id',
    ];

    public function author()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'author_id');
    }
}
