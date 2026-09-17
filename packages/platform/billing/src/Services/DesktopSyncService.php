<?php

namespace App\Services;

use App\Models\DesktopInstall;
use App\Models\DesktopSyncBatch;
use App\Models\DesktopSyncEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 OUT sync + Phase 5 IN pull — cloud hub for desktop events.
 * Does not apply events into SaaS tenant databases yet.
 */
class DesktopSyncService
{
    public function __construct(
        protected DesktopLicenceService $licences
    ) {}

    /**
     * @param  array{
     *   install_uuid:string,
     *   token:string,
     *   fingerprint:string,
     *   batch_uuid?:string|null,
     *   app_version?:string|null,
     *   events: array<int, array{event_id?:string,type?:string,occurred_at?:string,schema_version?:int,payload?:array}>
     * }  $input
     * @return array{
     *   batch: DesktopSyncBatch,
     *   accepted: int,
     *   duplicates: int,
     *   rejected: int,
     *   accepted_event_ids: list<string>,
     *   duplicate_event_ids: list<string>
     * }
     */
    public function pushOut(array $input): array
    {
        $this->assertTablesReady();

        $install = $this->licences->assertLicensedInstall($input);
        $events = $input['events'] ?? null;
        if (! is_array($events) || $events === []) {
            throw ValidationException::withMessages([
                'events' => 'Au moins un événement est requis.',
            ]);
        }

        $max = (int) config('sync.max_events_per_batch', 200);
        if (count($events) > $max) {
            throw ValidationException::withMessages([
                'events' => "Maximum {$max} événements par batch.",
            ]);
        }

        $batchUuid = trim((string) ($input['batch_uuid'] ?? ''));
        if ($batchUuid === '') {
            $batchUuid = (string) Str::uuid();
        }

        $existingBatch = DesktopSyncBatch::query()
            ->where('desktop_install_id', $install->id)
            ->where('uuid', $batchUuid)
            ->first();

        if ($existingBatch) {
            return [
                'batch' => $existingBatch,
                'accepted' => (int) $existingBatch->accepted_count,
                'duplicates' => (int) $existingBatch->duplicate_count,
                'rejected' => 0,
                'accepted_event_ids' => [],
                'duplicate_event_ids' => [],
                'idempotent_batch' => true,
            ];
        }

        $acceptedIds = [];
        $duplicateIds = [];
        $rejected = 0;

        $result = DB::transaction(function () use ($install, $input, $events, $batchUuid, &$acceptedIds, &$duplicateIds, &$rejected) {
            $batch = DesktopSyncBatch::create([
                'uuid' => $batchUuid,
                'desktop_install_id' => $install->id,
                'tenant_id' => $install->tenant_id,
                'product_key' => $install->product_key,
                'event_count' => count($events),
                'accepted_count' => 0,
                'duplicate_count' => 0,
                'status' => DesktopSyncBatch::STATUS_ACCEPTED,
                'received_at' => now(),
                'client_app_version' => $input['app_version'] ?? $install->app_version,
            ]);

            foreach ($events as $index => $raw) {
                if (! is_array($raw)) {
                    $rejected++;
                    continue;
                }

                $eventId = trim((string) ($raw['event_id'] ?? ''));
                $type = trim((string) ($raw['type'] ?? ''));
                $payload = $raw['payload'] ?? null;

                if ($eventId === '' || $type === '' || ! is_array($payload)) {
                    $rejected++;
                    continue;
                }

                if (strlen($type) > 128) {
                    $rejected++;
                    continue;
                }

                $exists = DesktopSyncEvent::query()
                    ->where('desktop_install_id', $install->id)
                    ->where('event_id', $eventId)
                    ->exists();

                if ($exists) {
                    $duplicateIds[] = $eventId;
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

                try {
                    DesktopSyncEvent::create([
                        'event_id' => $eventId,
                        'desktop_install_id' => $install->id,
                        'tenant_id' => $install->tenant_id,
                        'batch_id' => $batch->id,
                        'product_key' => $install->product_key,
                        'type' => $type,
                        'schema_version' => (int) ($raw['schema_version'] ?? 1),
                        'occurred_at' => $occurredAt,
                        'received_at' => now(),
                        'payload' => $payload,
                    ]);
                    $acceptedIds[] = $eventId;
                } catch (\Throwable) {
                    // Race on unique → treat as duplicate
                    $duplicateIds[] = $eventId;
                }
            }

            $accepted = count($acceptedIds);
            $duplicates = count($duplicateIds);
            $status = DesktopSyncBatch::STATUS_ACCEPTED;
            if ($rejected > 0 && $accepted === 0 && $duplicates === 0) {
                $status = DesktopSyncBatch::STATUS_REJECTED;
            } elseif ($rejected > 0 || ($duplicates > 0 && $accepted > 0)) {
                $status = DesktopSyncBatch::STATUS_PARTIAL;
            } elseif ($accepted === 0 && $duplicates > 0) {
                $status = DesktopSyncBatch::STATUS_ACCEPTED; // pure replay
            }

            $batch->update([
                'accepted_count' => $accepted,
                'duplicate_count' => $duplicates,
                'status' => $status,
                'meta' => [
                    'rejected_count' => $rejected,
                ],
            ]);

            $meta = $install->meta ?? [];
            $meta['last_sync_at'] = now()->toIso8601String();
            $meta['last_sync_batch_uuid'] = $batch->uuid;
            $meta['sync_event_count'] = (int) DesktopSyncEvent::query()
                ->where('desktop_install_id', $install->id)
                ->count();
            $install->update(['meta' => $meta]);

            return $batch->fresh();
        });

        return [
            'batch' => $result,
            'accepted' => count($acceptedIds),
            'duplicates' => count($duplicateIds),
            'rejected' => $rejected,
            'accepted_event_ids' => $acceptedIds,
            'duplicate_event_ids' => $duplicateIds,
            'idempotent_batch' => false,
        ];
    }

    /**
     * @param  array{install_uuid:string,token:string,fingerprint:string}  $input
     * @return array<string, mixed>
     */
    public function status(array $input): array
    {
        $this->assertTablesReady();
        $install = $this->licences->assertLicensedInstall($input);

        $lastEvent = DesktopSyncEvent::query()
            ->where('desktop_install_id', $install->id)
            ->orderByDesc('id')
            ->first();

        $lastBatch = DesktopSyncBatch::query()
            ->where('desktop_install_id', $install->id)
            ->orderByDesc('id')
            ->first();

        return [
            'install_uuid' => $install->uuid,
            'event_count' => (int) DesktopSyncEvent::query()->where('desktop_install_id', $install->id)->count(),
            'batch_count' => (int) DesktopSyncBatch::query()->where('desktop_install_id', $install->id)->count(),
            'tenant_event_count' => (int) DesktopSyncEvent::query()->where('tenant_id', $install->tenant_id)->count(),
            'last_event_id' => $lastEvent?->event_id,
            'last_event_type' => $lastEvent?->type,
            'last_received_at' => $lastEvent?->received_at?->toIso8601String(),
            'last_batch_uuid' => $lastBatch?->uuid,
            'last_batch_status' => $lastBatch?->status,
        ];
    }

    /**
     * Phase 5 IN sync — pull events from other installs of the same tenant.
     *
     * @param  array{
     *   install_uuid:string,
     *   token:string,
     *   fingerprint:string,
     *   after_id?:int|null,
     *   limit?:int|null
     * }  $input
     * @return array{events: list<array<string,mixed>>, next_after_id: int|null, count: int}
     */
    public function pullIn(array $input): array
    {
        $this->assertTablesReady();
        $install = $this->licences->assertLicensedInstall($input);

        $afterId = (int) ($input['after_id'] ?? 0);
        if ($afterId < 0) {
            $afterId = 0;
        }

        $limit = (int) ($input['limit'] ?? 0);
        if ($limit <= 0) {
            $limit = (int) config('sync.max_events_per_batch', 200);
        }
        $limit = min($limit, (int) config('sync.max_events_per_batch', 200));

        $rows = DesktopSyncEvent::query()
            ->where('tenant_id', $install->tenant_id)
            ->where('desktop_install_id', '!=', $install->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->with('install:id,uuid')
            ->get();

        $events = [];
        $nextAfterId = $afterId;

        foreach ($rows as $row) {
            $nextAfterId = (int) $row->id;
            $events[] = [
                'cloud_id' => (int) $row->id,
                'event_id' => $row->event_id,
                'type' => $row->type,
                'schema_version' => (int) $row->schema_version,
                'occurred_at' => optional($row->occurred_at)->toIso8601String(),
                'received_at' => optional($row->received_at)->toIso8601String(),
                'payload' => $row->payload ?? [],
                'source_install_uuid' => $row->install?->uuid,
            ];
        }

        $meta = $install->meta ?? [];
        $meta['last_sync_in_at'] = now()->toIso8601String();
        $meta['last_sync_in_after_id'] = $nextAfterId;
        $install->update(['meta' => $meta]);

        return [
            'events' => $events,
            'next_after_id' => $events === [] ? $afterId : $nextAfterId,
            'count' => count($events),
        ];
    }

    public function statsForInstall(DesktopInstall $install): array
    {
        if (! Schema::hasTable('desktop_sync_events')) {
            return [
                'event_count' => 0,
                'batch_count' => 0,
                'last_received_at' => null,
            ];
        }

        $last = DesktopSyncEvent::query()
            ->where('desktop_install_id', $install->id)
            ->orderByDesc('id')
            ->first();

        return [
            'event_count' => (int) DesktopSyncEvent::query()->where('desktop_install_id', $install->id)->count(),
            'batch_count' => (int) DesktopSyncBatch::query()->where('desktop_install_id', $install->id)->count(),
            'last_received_at' => $last?->received_at,
        ];
    }

    private function assertTablesReady(): void
    {
        if (! Schema::hasTable('desktop_sync_batches') || ! Schema::hasTable('desktop_sync_events')) {
            throw new \RuntimeException('Tables desktop_sync_* manquantes. Exécutez les migrations Control Center.');
        }
    }
}
