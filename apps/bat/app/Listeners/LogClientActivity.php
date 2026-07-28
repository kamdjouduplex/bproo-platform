<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;

/**
 * Handles ClientCreated and ClientUpdated events.
 * Phase 0: logs the activity.
 * Phase 1: will trigger CRM timeline entries and notifications.
 */
class LogClientActivity
{
    public function handle(object $event): void
    {
        $action = class_basename($event); // 'ClientCreated' or 'ClientUpdated'

        Log::info('client.activity', [
            'event'       => $action,
            'client_id'   => $event->client->id ?? null,
            'client_name' => $event->client->name ?? null,
            'tenant'      => $event->tenant->code ?? null,
        ]);
    }
}
