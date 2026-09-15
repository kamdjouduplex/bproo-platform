<?php

namespace Bproo\Platform\Desktop\Services;

use Bproo\Platform\Desktop\Models\DesktopOutboxEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OutboxWriter
{
    public function enqueue(string $type, array $payload, ?Carbon $occurredAt = null, int $schemaVersion = 1): DesktopOutboxEvent
    {
        $this->assertReady();

        return DesktopOutboxEvent::create([
            'event_id' => (string) Str::uuid(),
            'type' => $type,
            'schema_version' => $schemaVersion,
            'occurred_at' => $occurredAt ?: now(),
            'payload' => $payload,
            'status' => DesktopOutboxEvent::STATUS_PENDING,
        ]);
    }

    private function assertReady(): void
    {
        if (! Schema::hasTable('desktop_outbox_events')) {
            throw new \RuntimeException('Table desktop_outbox_events manquante. php artisan migrate');
        }
    }
}
