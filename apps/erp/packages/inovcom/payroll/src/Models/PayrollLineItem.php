<?php

namespace InovCom\Payroll\Models;

use InovCom\Kernel\TenantModel;

class PayrollLineItem extends TenantModel
{
    public const TYPE_BASE = 'base';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_DEDUCTION = 'deduction';
    public const TYPE_TAX = 'tax';
    public const TYPE_ABSENCE = 'absence';
    public const TYPE_LEAVE = 'leave';

    public const TYPE_UNPAID_DAYS = 'unpaid_days';

    public const TYPE_LABELS = [
        self::TYPE_BASE => 'Salaire de base',
        self::TYPE_BONUS => 'Prime',
        self::TYPE_DEDUCTION => 'Retenue',
        self::TYPE_TAX => 'Cotisation / impôt',
        self::TYPE_ABSENCE => 'Absence',
        self::TYPE_LEAVE => 'Congé',
        self::TYPE_UNPAID_DAYS => 'Jours non payés',
    ];

    protected $fillable = [
        'payroll_line_id',
        'type',
        'label',
        'amount',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function payrollLine()
    {
        return $this->belongsTo(PayrollLine::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
