<?php

namespace InovCom\Rh\Models;

use InovCom\Kernel\TenantModel;

class Employee extends TenantModel
{
    protected $table = 'employees';

    protected $fillable = [
        'code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'department',
        'contract_type',
        'hire_date',
        'end_date',
        'base_salary',
        'iban',
        'status',
        'notes',
    ];

    protected $casts = [
        'hire_date'   => 'date',
        'end_date'    => 'date',
        'base_salary' => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function payslips()
    {
        return $this->hasMany(Payslip::class)->orderByDesc('period_year')->orderByDesc('period_month');
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class)->orderByDesc('start_date');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function fullName(): string
    {
        return $this->first_name . ' ' . strtoupper($this->last_name);
    }

    public function initials(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    public function avatarColor(): string
    {
        $colors = [
            'bg-blue-500', 'bg-violet-500', 'bg-emerald-500',
            'bg-amber-500', 'bg-rose-500', 'bg-indigo-500',
            'bg-pink-500', 'bg-teal-500', 'bg-orange-500', 'bg-cyan-500',
        ];
        return $colors[ord($this->first_name[0] ?? 'A') % count($colors)];
    }

    public function contractLabel(): string
    {
        return match ($this->contract_type) {
            'cdi'        => 'CDI',
            'cdd'        => 'CDD',
            'stage'      => 'Stage',
            'freelance'  => 'Prestataire',
            'alternance' => 'Alternance',
            default      => ucfirst($this->contract_type),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active'     => 'Actif',
            'on_leave'   => 'En congé',
            'terminated' => 'Sorti',
            default      => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'active'     => 'badge-success',
            'on_leave'   => 'badge-warning',
            'terminated' => 'badge-danger',
            default      => 'badge-secondary',
        };
    }

    // ── Code generation ───────────────────────────────────────────────────────

    public static function generateCode(): string
    {
        $max = static::on('tenant')
            ->where('code', 'like', 'EMP%')
            ->pluck('code')
            ->map(fn(string $c) => (int) substr($c, 3))
            ->filter(fn(int $n) => $n > 0)
            ->max();

        return 'EMP' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('last_name')->orderBy('first_name');
    }
}
