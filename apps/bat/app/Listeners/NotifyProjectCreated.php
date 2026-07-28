<?php

namespace App\Listeners;

use App\Events\ProjectCreatedFromQuote;
use Illuminate\Support\Facades\Log;

/**
 * When a project is auto-created from a quote, notify the assigned PM
 * (or all directors/project managers).
 *
 * Phase 1: logs + in-app notification stub.
 * Phase 2: will send an email via Mailable.
 */
class NotifyProjectCreated
{
    public function handle(ProjectCreatedFromQuote $event): void
    {
        $project = $event->project;
        $quote   = $event->quote;

        Log::info('NotifyProjectCreated', [
            'project_code' => $project->code,
            'quote_code'   => $quote->code,
            'client_id'    => $project->client_id,
        ]);

        // Phase 2 TODO: send email/notification to project manager.
        // $pm = User::on('tenant')->find($project->assigned_to);
        // if ($pm) { $pm->notify(new ProjectAssignedNotification($project)); }
    }
}
