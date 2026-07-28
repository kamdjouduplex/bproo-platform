<?php

namespace InovCom\Tickets\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Tickets\Services\TicketsService;
use InovCom\Users\Models\User;
use Livewire\Component;

class TicketForm extends Component
{
    public string $title = '';
    public string $description = '';
    public string $category = '';
    public string $priority = 'normal';
    public ?int $assigned_to = null;

    public function mount(): void
    {
        if (!$this->can('tickets.create')) {
            abort(403);
        }
    }

    public function save(): void
    {
        if (!$this->can('tickets.create')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'title' => 'required|string|min:3|max:200',
            'description' => 'required|string|min:10|max:5000',
            'category' => 'nullable|string|max:100',
            'priority' => 'required|in:low,normal,high,urgent',
            'assigned_to' => 'nullable|exists:tenant.users,id',
        ], [], [
            'title' => 'titre',
            'description' => 'description',
            'category' => 'catégorie',
            'priority' => 'priorité',
            'assigned_to' => 'assigné à',
        ]);

        try {
            $ticket = app(TicketsService::class)->createTicket([
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => trim($data['category'] ?? '') !== '' ? trim($data['category']) : null,
                'priority' => $data['priority'],
                'assigned_to' => $data['assigned_to'] ?? null,
            ]);

            session()->flash('success', 'Ticket créé : ' . $ticket->ticket_number);
            $this->redirect(route('tenant.tickets.show', [
                'ticket' => $ticket->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('inovcom-tickets::livewire.tickets.form')
            ->layout('layouts.app', [
                'title' => 'Nouveau ticket',
                'subtitle' => 'Signaler un problème',
            ])
            ->with([
                'users' => $users,
                'priorities' => \InovCom\Tickets\Models\Ticket::priorityOptions(),
                'tenantCode' => $this->tenantCode(),
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
