<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a model transitions from one workflow status to another
 * via WorkflowStateMachine::transitionTo().
 *
 * Listeners can type-hint the model class or check $event->model::class
 * to react only to specific entities.
 */
class WorkflowTransitioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model   $model,
        public readonly string  $from,
        public readonly string  $to,
        public readonly ?int    $userId,
        public readonly ?string $reason,
    ) {}
}
