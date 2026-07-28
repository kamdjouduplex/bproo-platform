<?php

namespace InovCom\Tickets\Services;

use App\Services\StoreContextService;
use InovCom\Tickets\Models\Ticket;
use InovCom\Tickets\Models\TicketComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketsService
{
    /**
     * @param  array{title: string, description: string, category?: ?string, priority?: string, assigned_to?: ?int}  $data
     */
    public function createTicket(array $data, ?int $userId = null): Ticket
    {
        $userId = $userId ?? auth('tenant')->id();

        return DB::connection('tenant')->transaction(function () use ($data, $userId) {
            $ticket = Ticket::create([
                'ticket_number' => $this->generateTicketNumber(),
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'] ?? null,
                'status' => Ticket::STATUS_OPEN,
                'priority' => $data['priority'] ?? Ticket::PRIORITY_NORMAL,
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => $userId,
                'store_id' => $this->resolveStoreId(),
            ]);

            $this->addComment(
                $ticket,
                'Ticket ouvert.',
                TicketComment::TYPE_COMMENT,
                $userId
            );

            if ($ticket->assigned_to) {
                $this->logAssignment($ticket, null, (int) $ticket->assigned_to, $userId);
            }

            return $ticket->fresh(['creator', 'assignee']);
        });
    }

    public function addComment(
        Ticket $ticket,
        string $body,
        string $type = TicketComment::TYPE_COMMENT,
        ?int $userId = null
    ): TicketComment {
        if ($ticket->isClosed() && $type === TicketComment::TYPE_COMMENT) {
            throw new \RuntimeException('Impossible d\'ajouter un commentaire sur un ticket clôturé.');
        }

        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Le commentaire ne peut pas être vide.');
        }

        return TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId ?? auth('tenant')->id(),
            'body' => $body,
            'comment_type' => $type,
        ]);
    }

    public function updateStatus(Ticket $ticket, string $newStatus, ?string $note = null, ?int $userId = null): Ticket
    {
        $userId = $userId ?? auth('tenant')->id();
        $newStatus = $this->validateStatus($newStatus);
        $oldStatus = $ticket->status;

        if ($oldStatus === $newStatus) {
            return $ticket;
        }

        if ($ticket->isClosed()) {
            throw new \RuntimeException('Ce ticket est clôturé.');
        }

        if (!$this->canTransition($oldStatus, $newStatus)) {
            throw new \RuntimeException(
                'Transition impossible : ' . Ticket::statusLabel($oldStatus) . ' → ' . Ticket::statusLabel($newStatus)
            );
        }

        return DB::connection('tenant')->transaction(function () use ($ticket, $newStatus, $oldStatus, $note, $userId) {
            $ticket->status = $newStatus;

            if ($newStatus === Ticket::STATUS_RESOLVED) {
                $ticket->resolved_at = now();
            } elseif ($oldStatus === Ticket::STATUS_RESOLVED && $newStatus !== Ticket::STATUS_CLOSED) {
                $ticket->resolved_at = null;
            }

            if ($newStatus === Ticket::STATUS_CLOSED) {
                $ticket->closed_at = now();
                $ticket->closed_by = $userId;
            }

            $ticket->save();

            $message = 'Statut : ' . Ticket::statusLabel($oldStatus) . ' → ' . Ticket::statusLabel($newStatus);
            if ($note) {
                $message .= "\n" . trim($note);
            }

            $this->addComment($ticket, $message, TicketComment::TYPE_STATUS, $userId);

            return $ticket->fresh();
        });
    }

    public function assign(Ticket $ticket, ?int $assigneeId, ?int $userId = null): Ticket
    {
        $userId = $userId ?? auth('tenant')->id();

        if ($ticket->isClosed()) {
            throw new \RuntimeException('Impossible de modifier l\'assignation d\'un ticket clôturé.');
        }

        $oldId = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $newId = $assigneeId ? (int) $assigneeId : null;

        if ($oldId === $newId) {
            return $ticket;
        }

        $ticket->assigned_to = $newId;
        if ($ticket->status === Ticket::STATUS_OPEN && $newId) {
            $ticket->status = Ticket::STATUS_IN_PROGRESS;
        }
        $ticket->save();

        $this->logAssignment($ticket, $oldId, $newId, $userId);

        return $ticket->fresh(['assignee']);
    }

    public function updateTicket(Ticket $ticket, array $data, ?int $userId = null): Ticket
    {
        if ($ticket->isClosed()) {
            throw new \RuntimeException('Ce ticket est clôturé.');
        }

        if (array_key_exists('assigned_to', $data)) {
            $this->assign($ticket, $data['assigned_to'] ? (int) $data['assigned_to'] : null, $userId);
            $ticket->refresh();
        }

        $ticket->fill([
            'title' => $data['title'] ?? $ticket->title,
            'description' => $data['description'] ?? $ticket->description,
            'category' => array_key_exists('category', $data) ? $data['category'] : $ticket->category,
            'priority' => $data['priority'] ?? $ticket->priority,
        ]);
        $ticket->save();

        return $ticket->fresh(['assignee', 'creator']);
    }

    public function close(Ticket $ticket, ?string $note = null, ?int $userId = null): Ticket
    {
        if ($ticket->status !== Ticket::STATUS_RESOLVED) {
            throw new \RuntimeException('Marquez le ticket comme « Résolu » avant de le clôturer.');
        }

        return $this->updateStatus($ticket, Ticket::STATUS_CLOSED, $note, $userId);
    }

    public function reopen(Ticket $ticket, ?string $note = null, ?int $userId = null): Ticket
    {
        if ($ticket->status !== Ticket::STATUS_CLOSED) {
            throw new \RuntimeException('Seul un ticket clôturé peut être réouvert.');
        }

        return DB::connection('tenant')->transaction(function () use ($ticket, $note, $userId) {
            $ticket->status = Ticket::STATUS_IN_PROGRESS;
            $ticket->closed_at = null;
            $ticket->closed_by = null;
            $ticket->save();

            $message = 'Ticket réouvert.';
            if ($note) {
                $message .= "\n" . trim($note);
            }
            $this->addComment($ticket, $message, TicketComment::TYPE_STATUS, $userId);

            return $ticket->fresh();
        });
    }

    private function logAssignment(Ticket $ticket, ?int $fromId, ?int $toId, ?int $userId): void
    {
        $fromName = $fromId ? (\InovCom\Users\Models\User::find($fromId)?->name ?? "#{$fromId}") : 'Non assigné';
        $toName = $toId ? (\InovCom\Users\Models\User::find($toId)?->name ?? "#{$toId}") : 'Non assigné';

        $this->addComment(
            $ticket,
            "Assignation : {$fromName} → {$toName}",
            TicketComment::TYPE_ASSIGN,
            $userId
        );
    }

    private function canTransition(string $from, string $to): bool
    {
        $allowed = [
            Ticket::STATUS_OPEN => [Ticket::STATUS_IN_PROGRESS],
            Ticket::STATUS_IN_PROGRESS => [Ticket::STATUS_OPEN, Ticket::STATUS_RESOLVED],
            Ticket::STATUS_RESOLVED => [Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_CLOSED],
            Ticket::STATUS_CLOSED => [],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }

    private function validateStatus(string $status): string
    {
        if (!array_key_exists($status, Ticket::statusOptions())) {
            throw new \InvalidArgumentException('Statut invalide.');
        }

        return $status;
    }

    private function generateTicketNumber(): string
    {
        $year = now()->year;
        $last = Ticket::whereYear('created_at', $year)->orderByDesc('id')->first();
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last->ticket_number, $m)) {
            $next = (int) $m[1] + 1;
        }

        return 'TKT-' . $year . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function resolveStoreId(): ?int
    {
        if (!Schema::connection('tenant')->hasColumn('tickets', 'store_id')) {
            return null;
        }

        return app(StoreContextService::class)->currentStoreId();
    }
}
