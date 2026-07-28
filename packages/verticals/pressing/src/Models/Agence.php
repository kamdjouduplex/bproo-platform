<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Agence extends TenantModel
{
    protected $table = 'agences';

    protected $fillable = [
        'code',
        'name',
        'country',
        'city',
        'location',
        'phone',
        'email',
        'manager_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(PressingClient::class, 'agence_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PressingOrder::class, 'agence_id');
    }
}
