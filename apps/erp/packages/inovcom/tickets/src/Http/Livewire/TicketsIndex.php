<?php

namespace InovCom\Tickets\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Tickets\Models\Ticket;
use Livewire\Component;
use Livewire\WithPagination;

class TicketsIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'open';
    public string $priorityFilter = 'all';
    public string $assignedFilter = 'all';
    public int $perPage = 20;

    public function mount(): void
    {
        $assigned = request()->query('assigned');
        if (in_array($assigned, ['mine', 'unassigned', 'all'], true)) {
            $this->assignedFilter = $assigned;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->priorityFilter = 'all';
        $this->assignedFilter = 'all';
        $this->resetPage();
    }

    public function render()
    {
        if (!$this->can('tickets.view')) {
            abort(403);
        }

        $userId = Auth::guard('tenant')->id();

        $query = Ticket::query()
            ->with(['creator', 'assignee'])
            ->when($this->search !== '', function ($q) {
                $term = '%' . strtolower(trim($this->search)) . '%';
                $q->where(function ($q2) use ($term) {
                    $q2->whereRaw('LOWER(ticket_number) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->priorityFilter !== 'all', fn ($q) => $q->where('priority', $this->priorityFilter))
            ->when($this->assignedFilter === 'mine' && $userId, fn ($q) => $q->where('assigned_to', $userId))
            ->when($this->assignedFilter === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByDesc('updated_at');

        $stats = [
            'open' => Ticket::where('status', Ticket::STATUS_OPEN)->count(),
            'in_progress' => Ticket::where('status', Ticket::STATUS_IN_PROGRESS)->count(),
            'resolved' => Ticket::where('status', Ticket::STATUS_RESOLVED)->count(),
        ];

        return view('inovcom-tickets::livewire.tickets.index')
            ->layout('layouts.app', [
                'title' => 'Tickets',
                'subtitle' => 'Suivi des incidents et demandes',
            ])
            ->with([
                'tickets' => $query->paginate($this->perPage),
                'stats' => $stats,
                'tenantCode' => $this->tenantCode(),
                'canCreate' => $this->can('tickets.create'),
            ]);
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
