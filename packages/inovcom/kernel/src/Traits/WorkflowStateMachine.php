<?php

namespace InovCom\Kernel\Traits;

use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Kernel\Models\AuditLog;

/**
 * Enforces a state machine on any model that has a `status` column.
 *
 * Usage — add this trait to the model and implement `allowedTransitions()`:
 *
 *   use WorkflowStateMachine;
 *
 *   public function allowedTransitions(): array
 *   {
 *       return [
 *           'draft'  => ['sent'],
 *           'sent'   => ['accepted', 'refused'],
 *           'accepted' => [],
 *           'refused'  => ['draft'],
 *       ];
 *   }
 *
 * Then change status via:
 *   $quote->transitionTo('sent', auth('tenant')->id());
 *
 * NEVER do: $quote->status = 'sent'; $quote->save();
 */
trait WorkflowStateMachine
{
    /**
     * Define allowed transitions per status.
     * Must be implemented by the model using this trait.
     *
     * @return array<string, string[]>  ['from_status' => ['to_status', ...], ...]
     */
    abstract public function allowedTransitions(): array;

    /**
     * Check if transitioning to $status is allowed from the current status.
     */
    public function canTransitionTo(string $status): bool
    {
        $transitions = $this->allowedTransitions();
        $allowed = $transitions[$this->status] ?? [];
        return in_array($status, $allowed, true);
    }

    /**
     * Perform the transition, write audit log, and fire WorkflowTransitioned event.
     *
     * @throws InvalidWorkflowTransitionException
     */
    public function transitionTo(string $status, ?int $userId = null, ?string $reason = null): void
    {
        if (!$this->canTransitionTo($status)) {
            throw new InvalidWorkflowTransitionException(
                sprintf(
                    'Cannot transition [%s] from "%s" to "%s". Allowed: [%s].',
                    $this->getTable(),
                    $this->status,
                    $status,
                    implode(', ', $this->allowedTransitions()[$this->status] ?? [])
                )
            );
        }

        $from = $this->status;
        $userId = $userId ?? Auditable::currentUserId();

        $this->status = $status;

        // Stamp convenience timestamps when entering key states.
        $this->stampTransitionTimestamp($status);

        $this->save();

        // Write to audit log.
        AuditLog::record($this, 'status_changed', $userId, [
            'old' => ['status' => $from],
            'new' => ['status' => $status, 'reason' => $reason],
        ]);

        // Fire a generic workflow event so listeners can react.
        event(new \App\Events\WorkflowTransitioned($this, $from, $status, $userId, $reason));
    }

    /**
     * Auto-set timestamp columns when entering well-known states.
     * Models can override this for custom behaviour.
     */
    protected function stampTransitionTimestamp(string $status): void
    {
        $stamps = [
            'sent'      => 'sent_at',
            'accepted'  => 'accepted_at',
            'refused'   => 'refused_at',
            'paid'      => 'paid_at',
            'completed' => 'completed_at',
            'closed'    => 'closed_at',
            'validated' => 'validated_at',
            'received'  => 'received_at',
        ];

        if (isset($stamps[$status]) && $this->hasColumn($stamps[$status])) {
            $this->{$stamps[$status]} = now();
        }
    }

    /**
     * Check if a given column exists on the model's table (cached per class).
     */
    protected function hasColumn(string $column): bool
    {
        return in_array($column, $this->getFillable(), true)
            || array_key_exists($column, $this->getCasts());
    }
}
