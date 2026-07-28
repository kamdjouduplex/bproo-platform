<?php

namespace InovCom\Tickets\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Tickets\Models\Ticket;
use InovCom\Tickets\Services\TicketsService;
use InovCom\Users\Models\User;
use Livewire\Component;

class TicketShow extends Component
{
    public int $ticketId;

    public string $newComment = '';
    public string $statusNote = '';
    public string $newStatus = '';
    public ?int $assigned_to = null;

    public function mount(Ticket $ticket): void
    {
        if (!$this->can('tickets.view')) {
            abort(403);
        }

        $this->ticketId = $ticket->id;
        $this->assigned_to = $ticket->assigned_to;
        $this->newStatus = $ticket->status;
    }

    public function addComment(): void
    {
        if (!$this->can('tickets.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $ticket = Ticket::findOrFail($this->ticketId);
            app(TicketsService::class)->addComment($ticket, $this->newComment);
            $this->newComment = '';
            session()->flash('success', 'Commentaire ajouté.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function changeStatus(): void
    {
        if (!$this->can('tickets.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $ticket = Ticket::findOrFail($this->ticketId);
            app(TicketsService::class)->updateStatus(
                $ticket,
                $this->newStatus,
                trim($this->statusNote) !== '' ? trim($this->statusNote) : null
            );
            $this->statusNote = '';
            $ticket->refresh();
            $this->newStatus = $ticket->status;
            $this->assigned_to = $ticket->assigned_to;
            session()->flash('success', 'Statut mis à jour.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function assignTicket(): void
    {
        if (!$this->can('tickets.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $ticket = Ticket::findOrFail($this->ticketId);
            app(TicketsService::class)->assign($ticket, $this->assigned_to);
            $ticket->refresh();
            $this->newStatus = $ticket->status;
            session()->flash('success', 'Assignation mise à jour.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeTicket(): void
    {
        if (!$this->can('tickets.close')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $ticket = Ticket::findOrFail($this->ticketId);
            app(TicketsService::class)->close(
                $ticket,
                trim($this->statusNote) !== '' ? trim($this->statusNote) : null
            );
            $this->statusNote = '';
            $ticket->refresh();
            $this->newStatus = $ticket->status;
            session()->flash('success', 'Ticket clôturé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function reopenTicket(): void
    {
        if (!$this->can('tickets.close')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        try {
            $ticket = Ticket::findOrFail($this->ticketId);
            app(TicketsService::class)->reopen(
                $ticket,
                trim($this->statusNote) !== '' ? trim($this->statusNote) : null
            );
            $this->statusNote = '';
            $ticket->refresh();
            $this->newStatus = $ticket->status;
            session()->flash('success', 'Ticket réouvert.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $ticket = Ticket::with(['comments.author', 'creator', 'assignee', 'closer'])
            ->findOrFail($this->ticketId);

        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('inovcom-tickets::livewire.tickets.show')
            ->layout('layouts.app', [
                'title' => $ticket->ticket_number,
                'subtitle' => $ticket->title,
            ])
            ->with([
                'ticket' => $ticket,
                'users' => $users,
                'statuses' => Ticket::statusOptions(),
                'tenantCode' => $this->tenantCode(),
                'canUpdate' => $this->can('tickets.update') && $ticket->isEditable(),
                'canClose' => $this->can('tickets.close'),
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
