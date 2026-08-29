<?php

namespace InovCom\Debts\Models;

use InovCom\Clients\Models\Client;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;
use Illuminate\Support\Facades\Schema;

class Debt extends TenantModel
{
    protected $fillable = [
        'reference',
        'client_id',
        'total_amount',
        'balance',
        'due_date',
        'opened_at',
        'status',
        'description',
        'sale_id',
        'created_by',
        'is_validated',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'due_date' => 'date',
        'opened_at' => 'date',
        'is_validated' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function payments()
    {
        return $this->hasMany(DebtPayment::class)->orderBy('payment_date', 'desc');
    }

    public function schedules()
    {
        return $this->hasMany(DebtSchedule::class)->orderBy('due_date');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Optional relation — only used when the Sales package is installed.
     */
    public function sale()
    {
        if (! class_exists(\InovCom\Sales\Models\Sale::class)) {
            return $this->belongsTo(static::class, 'sale_id')->whereRaw('0 = 1');
        }

        return $this->belongsTo(\InovCom\Sales\Models\Sale::class);
    }

    public function isValidated(): bool
    {
        return (bool) $this->is_validated;
    }

    public static function supportsValidationWorkflow(): bool
    {
        return Schema::connection('tenant')->hasTable('debts')
            && Schema::connection('tenant')->hasColumn('debts', 'is_validated')
            && Schema::connection('tenant')->hasColumn('debts', 'validated_by')
            && Schema::connection('tenant')->hasColumn('debts', 'validated_at');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function updateStatus(): void
    {
        if ($this->balance <= 0) {
            $this->status = 'paid';
        } elseif ($this->balance < $this->total_amount) {
            $this->status = 'partial';
        } elseif ($this->due_date && $this->due_date->isPast()) {
            $this->status = 'overdue';
        } else {
            $this->status = 'open';
        }
        $this->save();
    }
}
