<?php

namespace Bproo\Platform\Desktop\Services;

use Bproo\Platform\Desktop\Models\DesktopInboxEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Applies inbox events locally. MVP: ping + acknowledge sale.created (full POS replay later).
 */
class EventApplier
{
    /**
     * @return array{applied:int, failed:int, ignored:int}
     */
    public function applyPending(?int $limit = null): array
    {
        if (! Schema::hasTable('desktop_inbox_events')) {
            throw new \RuntimeException('Table desktop_inbox_events manquante. php artisan migrate');
        }

        $limit = $limit ?: (int) config('desktop.sync_batch_size', 50);
        $pending = DesktopInboxEvent::query()
            ->where('status', DesktopInboxEvent::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $applied = 0;
        $failed = 0;
        $ignored = 0;

        foreach ($pending as $event) {
            try {
                $result = $this->applyOne($event);
                if ($result === 'applied') {
                    $event->status = DesktopInboxEvent::STATUS_APPLIED;
                    $event->applied_at = now();
                    $event->last_error = null;
                    $applied++;
                } elseif ($result === 'ignored') {
                    $event->status = DesktopInboxEvent::STATUS_IGNORED;
                    $event->applied_at = now();
                    $ignored++;
                }
                $event->save();
            } catch (\Throwable $e) {
                $event->status = DesktopInboxEvent::STATUS_FAILED;
                $event->last_error = mb_substr($e->getMessage(), 0, 2000);
                $event->save();
                $failed++;
                Log::warning('desktop.inbox.apply_failed', [
                    'event_id' => $event->event_id,
                    'type' => $event->type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return compact('applied', 'failed', 'ignored');
    }

    private function applyOne(DesktopInboxEvent $event): string
    {
        return match ($event->type) {
            'sync.ping', 'desktop.enqueue.test' => 'applied',
            'sale.created' => $this->applySaleCreated($event),
            default => $this->ignoreUnknown($event),
        };
    }

    private function applySaleCreated(DesktopInboxEvent $event): string
    {
        // MVP: acknowledge receipt so multi-PC OUT→IN pipe is proven.
        // Full local sale+stock replay needs UUID lines + projector (follow-up).
        $payload = $event->payload ?? [];
        Log::info('desktop.inbox.sale_created_received', [
            'event_id' => $event->event_id,
            'source_install_uuid' => $event->source_install_uuid,
            'sale_number' => $payload['sale_number'] ?? null,
            'total' => $payload['total'] ?? null,
        ]);
        $event->last_error = 'ack_only: sale projector not implemented yet';

        return 'applied';
    }

    private function ignoreUnknown(DesktopInboxEvent $event): string
    {
        $event->last_error = 'unknown_type: '.$event->type;

        return 'ignored';
    }
}
