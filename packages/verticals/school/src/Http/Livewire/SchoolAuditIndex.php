<?php

namespace School\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ResolvesTenantCode;

class SchoolAuditIndex extends Component
{
    use ResolvesTenantCode;
    use WithPagination;

    public string $filterTable = '';

    public string $filterUser = '';

    public string $filterEvent = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 25;

    public string $loadError = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedFilterTable(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUser(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEvent(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->filterTable = '';
        $this->filterUser = '';
        $this->filterEvent = '';
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->loadError = '';
        $this->resetPage();
    }

    public function render()
    {
        $hasTable = false;
        $logs = null;
        $tables = collect();
        $users = collect();
        $rows = [];
        $this->loadError = '';

        try {
            $schema = Schema::connection('tenant');
            $hasTable = $schema->hasTable('audit_logs')
                && $schema->hasColumn('audit_logs', 'auditable_type')
                && $schema->hasColumn('audit_logs', 'event')
                && $schema->hasColumn('audit_logs', 'created_at');
        } catch (\Throwable $e) {
            Log::warning('school.audit schema: '.$e->getMessage());
            $hasTable = false;
        }

        if ($hasTable) {
            try {
                $query = DB::connection('tenant')->table('audit_logs')
                    ->when($this->filterTable !== '', fn ($q) => $q->where('auditable_type', $this->filterTable))
                    ->when($this->filterUser !== '', fn ($q) => $q->where('user_id', (int) $this->filterUser))
                    ->when($this->filterEvent !== '', fn ($q) => $q->where('event', $this->filterEvent))
                    ->when($this->dateFrom !== '', fn ($q) => $q->where('created_at', '>=', $this->dateFrom.' 00:00:00'))
                    ->when($this->dateTo !== '', fn ($q) => $q->where('created_at', '<=', $this->dateTo.' 23:59:59'))
                    ->orderByDesc('id');

                $logs = $query->paginate($this->perPage);
                $tables = DB::connection('tenant')->table('audit_logs')
                    ->select('auditable_type')
                    ->distinct()
                    ->orderBy('auditable_type')
                    ->pluck('auditable_type')
                    ->filter()
                    ->values();
                $users = DB::connection('tenant')->table('users')->orderBy('name')->get(['id', 'name']);
                $userMap = $users->pluck('name', 'id')->all();

                foreach ($logs as $log) {
                    $rows[] = $this->presentLog($log, $userMap);
                }
            } catch (\Throwable $e) {
                Log::error('school.audit query: '.$e->getMessage(), ['exception' => $e]);
                $this->loadError = 'Impossible de lire le journal d’audit. Vérifiez que la table audit_logs est migrée (tenant:migrate).';
                $hasTable = false;
                $logs = null;
            }
        }

        return view('school::livewire.school.audit.index', [
            'hasTable' => $hasTable,
            'logs' => $logs,
            'rows' => $rows,
            'tables' => $tables,
            'users' => $users,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'Ecole — Journal d’audit',
            'subtitle' => 'Traçabilité des actions critiques.',
        ]);
    }

    /**
     * @param  array<int|string, string>  $userMap
     * @return array<string, mixed>
     */
    protected function presentLog(object $log, array $userMap): array
    {
        $old = $this->decodeValues($log->old_values ?? null);
        $new = $this->decodeValues($log->new_values ?? null);
        $skip = ['password', 'remember_token', 'qr_svg', 'photo_path'];
        $changes = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            if (in_array((string) $key, $skip, true)) {
                continue;
            }
            $changes[] = [
                'key' => (string) $key,
                'old' => $this->stringifyValue($old[$key] ?? null),
                'new' => $this->stringifyValue($new[$key] ?? null),
            ];
        }

        $created = $log->created_at ?? null;
        try {
            $createdLabel = $created ? \Carbon\Carbon::parse($created)->format('d/m/Y H:i:s') : '—';
        } catch (\Throwable) {
            $createdLabel = (string) $created;
        }

        return [
            'created' => $createdLabel,
            'user' => $log->user_id ? ($userMap[$log->user_id] ?? $userMap[(int) $log->user_id] ?? '#'.$log->user_id) : '—',
            'type' => (string) ($log->auditable_type ?? ''),
            'id' => $log->auditable_id ?? '—',
            'event' => (string) ($log->event ?? ''),
            'changes' => $changes,
            'ip' => $log->ip_address ?? '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeValues(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_object($raw)) {
            return (array) $raw;
        }
        if (! is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '∅';
        }
        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }
        $text = is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        if (function_exists('iconv')) {
            $text = (string) @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        }
        if (mb_strlen($text) > 80) {
            return mb_substr($text, 0, 80).'…';
        }

        return $text;
    }
}
