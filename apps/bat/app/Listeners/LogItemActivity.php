<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;

/**
 * Handles ItemCreated, ItemUpdated, ItemDeleted events.
 * Phase 0: logs the activity.
 * Phase 1: will invalidate catalog caches and notify relevant users.
 */
class LogItemActivity
{
    public function handle(object $event): void
    {
        $action = class_basename($event);

        Log::info('item.activity', [
            'event'     => $action,
            'item_id'   => $event->item->id ?? null,
            'item_name' => $event->item->name ?? null,
            'tenant'    => $event->tenant->code ?? null,
        ]);
    }
}
