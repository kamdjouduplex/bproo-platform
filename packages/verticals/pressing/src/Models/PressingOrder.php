<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PressingOrder extends TenantModel
{
    use SoftDeletes;

    protected $table = 'pressing_orders';

    protected $fillable = [
        'number',
        'agence_id',
        'client_id',
        'receptionist_id',
        'assigned_user_id',
        'received_at',
        'due_at',
        'current_stage_id',
        'status',
        'sorting_status',
        'sorting_completed_at',
        'sorted_by',
        'billing_mode',
        'total_weight_kg',
        'weight_unit_price',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'amount_paid',
        'balance',
        'credit_status',
        'credit_amount',
        'credit_notes',
        'credit_requested_by',
        'credit_requested_at',
        'credit_validated_by',
        'credit_validated_at',
        'credit_rejection_reason',
        'qr_token',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'due_at' => 'datetime',
        'sorting_completed_at' => 'datetime',
        'credit_requested_at' => 'datetime',
        'credit_validated_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'total_weight_kg' => 'decimal:3',
        'weight_unit_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (empty($order->qr_token)) {
                $order->qr_token = (string) Str::uuid();
            }
        });
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PressingClient::class, 'client_id');
    }

    public function receptionist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptionist_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PressingOrderItem::class, 'order_id');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(PressingOrderPiece::class, 'order_id')->orderBy('piece_index');
    }

    public function constitutionLines(): HasMany
    {
        return $this->hasMany(PressingOrderConstitutionLine::class, 'order_id')->orderBy('sort_order');
    }

    public function constitutionSummary(): string
    {
        $lines = $this->relationLoaded('constitutionLines')
            ? $this->constitutionLines
            : $this->constitutionLines()->with('articleType')->get();

        return \Pressing\Support\PressingConstitution::summary($lines);
    }

    public function isSortingCompleted(): bool
    {
        return $this->sorting_status === 'completed';
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->balance <= 0;
    }

    public function hasApprovedCredit(): bool
    {
        return app(\Pressing\Services\PressingSettlementService::class)->hasApprovedCredit($this);
    }

    public function canBeDelivered(): bool
    {
        return app(\Pressing\Services\PressingSettlementService::class)->canDeliver($this);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PressingPayment::class, 'order_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PressingDelivery::class, 'order_id');
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(OrderStageHistory::class, 'order_id')->orderBy('moved_at');
    }

    public function recalculateTotals(): void
    {
        if ($this->billing_mode === 'weight_global' && (float) $this->total_weight_kg > 0) {
            $subtotal = round((float) $this->total_weight_kg * (float) $this->weight_unit_price, 2);
        } else {
            $subtotal = (float) $this->items()->sum('line_total');
        }

        $total = max(0, $subtotal - (float) $this->discount_amount + (float) $this->tax_amount);
        $paid = (float) $this->payments()->sum('amount');

        $this->forceFill([
            'subtotal' => $subtotal,
            'total' => $total,
            'amount_paid' => $paid,
            'balance' => max(0, $total - $paid),
        ])->save();
    }
}
