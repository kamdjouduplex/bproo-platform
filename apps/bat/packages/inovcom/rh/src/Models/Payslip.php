<?php

namespace InovCom\Rh\Models;

use InovCom\Kernel\TenantModel;

class Payslip extends TenantModel
{
    protected $table = 'payslips';

    protected $fillable = [
        'code',
        'employee_id',
        'period_month',
        'period_year',
        'base_salary',
        'additions',
        'deductions',
        'gross_salary',
        'net_salary',
        'status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'additions'   => 'array',
        'deductions'  => 'array',
        'base_salary' => 'decimal:2',
        'gross_salary'=> 'decimal:2',
        'net_salary'  => 'decimal:2',
        'paid_at'     => 'date',
    ];

    private static array $monthNames = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function periodLabel(): string
    {
        return (self::$monthNames[$this->period_month] ?? '?') . ' ' . $this->period_year;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'     => 'Brouillon',
            'validated' => 'Validée',
            'paid'      => 'Payée',
            default     => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'draft'     => 'badge-secondary',
            'validated' => 'badge-info',
            'paid'      => 'badge-success',
            default     => 'badge-secondary',
        };
    }

    // ── Code generation ───────────────────────────────────────────────────────

    public static function generateCode(): string
    {
        $max = static::on('tenant')
            ->where('code', 'like', 'PAY%')
            ->pluck('code')
            ->map(fn(string $c) => (int) substr($c, 3))
            ->filter(fn(int $n) => $n > 0)
            ->max();

        return 'PAY' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    public static function monthName(int $month): string
    {
        return self::$monthNames[$month] ?? '';
    }

    public static function allMonths(): array
    {
        return self::$monthNames;
    }
}
