<?php

namespace Bproo\Platform\Desktop\Services;

use Bproo\Platform\Desktop\Models\DesktopOutboxEvent;
use Illuminate\Support\Facades\Schema;

class OutboxPusher
{
    public function __construct(
        protected ControlCenterClient $cc,
        protected RuntimeStateStore $state
    ) {}

    /**
     * @return array{accepted:int,duplicates:int,rejected:int,sent_ids:list<string>}
     */
    public function push(?int $limit = null): array
    {
        if (! Schema::hasTable('desktop_outbox_events')) {
            throw new \RuntimeException('Table desktop_outbox_events manquante. php artisan migrate');
        }

        $s = $this->state->requireActivated();
        $limit = $limit ?: (int) config('desktop.sync_batch_size', 50);

        $pending = DesktopOutboxEvent::query()
            ->where('status', DesktopOutboxEvent::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            return ['accepted' => 0, 'duplicates' => 0, 'rejected' => 0, 'sent_ids' => []];
        }

        $events = $pending->map(fn (DesktopOutboxEvent $e) => [
            'event_id' => $e->event_id,
            'type' => $e->type,
            'occurred_at' => optional($e->occurred_at)->toIso8601String(),
            'schema_version' => $e->schema_version,
            'payload' => $e->payload,
        ])->all();

        $json = $this->cc->postJson('/api/sync/out', [
            'install_uuid' => $s['install_uuid'],
            'token' => $s['token'],
            'fingerprint' => $s['fingerprint'],
            'batch_uuid' => $this->cc->newBatchUuid(),
            'app_version' => $this->cc->appVersion(),
            'events' => $events,
        ]);

        $acceptedIds = $json['accepted_event_ids'] ?? [];
        $duplicateIds = $json['duplicate_event_ids'] ?? [];
        $okIds = array_values(array_unique(array_merge($acceptedIds, $duplicateIds)));

        foreach ($pending as $event) {
            $event->attempts = ((int) $event->attempts) + 1;
            if (in_array($event->event_id, $okIds, true)) {
                $event->status = DesktopOutboxEvent::STATUS_SENT;
                $event->sent_at = now();
                $event->last_error = null;
            } else {
                $event->status = DesktopOutboxEvent::STATUS_FAILED;
                $event->last_error = 'Not accepted by Control Center in this batch.';
            }
            $event->save();
        }

        $this->state->merge([
            'last_sync_out_at' => now()->toIso8601String(),
            'token' => $json['token'] ?? $s['token'],
        ]);

        return [
            'accepted' => (int) ($json['accepted'] ?? count($acceptedIds)),
            'duplicates' => (int) ($json['duplicates'] ?? count($duplicateIds)),
            'rejected' => (int) ($json['rejected'] ?? 0),
            'sent_ids' => $okIds,
        ];
    }
}
