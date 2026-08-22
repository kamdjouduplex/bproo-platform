<?php

namespace School\Models;

use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class SchoolRoom extends TenantModel
{
    use Auditable;

    protected $table = 'school_rooms';

    protected $fillable = [
        'name',
        'capacity',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('school_rooms');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function activeList()
    {
        if (! self::tableReady()) {
            return collect();
        }

        return self::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function courses()
    {
        return $this->hasMany(SchoolCourse::class, 'room_id');
    }

    public function slots()
    {
        return $this->hasMany(SchoolTimetableSlot::class, 'room_id');
    }

    public function displayLabel(): string
    {
        return $this->capacity
            ? $this->name.' ('.$this->capacity.' pl.)'
            : $this->name;
    }
}
