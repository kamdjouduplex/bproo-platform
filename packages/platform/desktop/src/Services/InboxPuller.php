<?php

namespace Bproo\Platform\Desktop\Services;

use Bproo\Platform\Desktop\Models\DesktopInboxEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class InboxPuller
{
    public function __construct(
        protected ControlCenterClient $cc,
        protected RuntimeStateStore $state
    ) {}

    /**
     * @return array{pulled:int, next_after_id:int}
     */
    public function pull(?int $limit = null): array
    {
        if (! Schema::hasTable('desktop_inbox_events')) {
            throw new \RuntimeException('Table desktop_inbox_events manquante. php artisan migrate');
        }

        $s = $this->state->requireActivated();
        $afterId = (int) ($s['sync_in_after_id'] ?? 0);
        $limit = $limit ?: (int) config('desktop.sync_batch_size', 50);

        $json = $this->cc->postJson('/api/sync/in', [
            'install_uuid' => $s['install_uuid'],
            'token' => $s['token'],
            'fingerprint' => $s['fingerprint'],
            'after_id' => $afterId,
            'limit' => $limit,
        ]);

        $events = $json['events'] ?? [];
        if (! is_array($events)) {
            $events = [];
        }

        $pulled = 0;
        foreach ($events as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $eventId = trim((string) ($raw['event_id'] ?? ''));
            $type = trim((string) ($raw['type'] ?? ''));
            $payload = $raw['payload'] ?? null;
            if ($eventId === '' || $type === '' || ! is_array($payload)) {
                continue;
            }

            $cloudId = isset($raw['cloud_id']) ? (int) $raw['cloud_id'] : null;
            $exists = DesktopInboxEvent::query()->where('event_id', $eventId)->exists();
            if (! $exists && $cloudId) {
                $exists = DesktopInboxEvent::query()->where('cloud_id', $cloudId)->exists();
            }
            if ($exists) {
                continue;
            }

            $occurredAt = null;
            if (! empty($raw['occurred_at'])) {
                try {
                    $occurredAt = Carbon::parse((string) $raw['occurred_at']);
                } catch (\Throwable) {
                    $occurredAt = null;
                }
            }

            DesktopInboxEvent::create([
                'cloud_id' => $cloudId ?: null,
                'event_id' => $eventId,
                'type' => $type,
                'schema_version' => (int) ($raw['schema_version'] ?? 1),
                'occurred_at' => $occurredAt,
                'payload' => $payload,
                'source_install_uuid' => $raw['source_install_uuid'] ?? null,
                'status' => DesktopInboxEvent::STATUS_PENDING,
            ]);
            $pulled++;
        }

        $nextAfterId = (int) ($json['next_after_id'] ?? $afterId);
        $this->state->merge([
            'sync_in_after_id' => $nextAfterId,
            'last_sync_in_at' => now()->toIso8601String(),
            'token' => $json['token'] ?? $s['token'],
        ]);

        return [
            'pulled' => $pulled,
            'next_after_id' => $nextAfterId,
        ];
    }
}
