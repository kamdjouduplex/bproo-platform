<?php

namespace InovCom\Suivi\Models;

use Carbon\Carbon;
use InovCom\Kernel\TenantModel;
use InovCom\Projets\Models\Project;
use InovCom\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends TenantModel
{
    protected $fillable = [
        'code', 'project_id', 'phase_id', 'assigned_to',
        'title', 'description', 'notes',
        'status', 'priority',
        'period_type', 'period_date', 'position',
        'planned_start', 'planned_end', 'actual_start', 'actual_end',
        'estimated_hours', 'actual_hours',
        'is_validated', 'validated_at', 'validated_by',
        'due_date', 'assignee_ids',
    ];

    protected $casts = [
        'period_date'  => 'date',
        'due_date'     => 'date',
        'is_validated' => 'boolean',
        'validated_at' => 'datetime',
        'assignee_ids' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function scopeForPeriod($query, string $periodType, string $periodDate)
    {
        if ($periodType === 'day') {
            return $query->where('period_date', $periodDate);
        }
        $start = Carbon::parse($periodDate)->startOfWeek(Carbon::MONDAY);
        $end   = $start->copy()->addDays(5); // Monday → Saturday
        return $query->whereBetween('period_date', [$start->toDateString(), $end->toDateString()]);
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'todo'        => __('À faire'),
            'in_progress' => __('En cours'),
            'done'        => __('Terminé'),
            default       => $this->status,
        };
    }

    public static function generateCode(): string
    {
        $max = static::on('tenant')
            ->where('code', 'like', 'TSK%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, 3))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();

        return 'TSK' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
