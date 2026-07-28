<?php

namespace App\Listeners;

use App\Events\WorkflowTransitioned;
use Illuminate\Support\Facades\Log;

/**
 * Listens to WorkflowTransitioned and writes a structured log entry.
 * In Phase 1 this will also dispatch notifications and trigger side-effects.
 */
class LogWorkflowTransition
{
    public function handle(WorkflowTransitioned $event): void
    {
        Log::info('workflow.transition', [
            'table'   => $event->model->getTable(),
            'id'      => $event->model->getKey(),
            'from'    => $event->from,
            'to'      => $event->to,
            'user_id' => $event->userId,
            'reason'  => $event->reason,
        ]);
    }
}
