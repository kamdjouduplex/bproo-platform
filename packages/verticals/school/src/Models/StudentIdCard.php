<?php

namespace School\Models;

use Illuminate\Support\Str;
use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use School\Support\SchoolQrCode;

class StudentIdCard extends TenantModel
{
    use Auditable;

    protected $table = 'student_id_cards';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'batch_code',
        'qr_token',
        'qr_svg',
        'barcode_data',
        'generated_at',
    ];

    protected $hidden = ['qr_svg'];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'student_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    protected static function booted(): void
    {
        static::creating(function (StudentIdCard $card) {
            if (empty($card->qr_token)) {
                $card->qr_token = (string) Str::uuid();
            }
        });

        static::created(function (StudentIdCard $card) {
            if (empty($card->qr_svg)) {
                SchoolQrCode::ensureCached($card);
            }
        });
    }
}
