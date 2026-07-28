<?php

namespace App\Livewire\Tenant;

use App\Services\PendingActionsService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Pressing\Models\PressingNotification;
use Pressing\Support\PressingSettings;

class NotificationBell extends Component
{
    public int $totalCount = 0;

    /** @var array<int, array<string, mixed>> */
    public array $groups = [];

    /** @var array<int, array<string, mixed>> */
    public array $inbox = [];

    public int $inboxUnread = 0;

    public string $tab = 'actions';

    public function mount(): void
    {
        $this->refreshNotifications();
    }

    public function refreshNotifications(): void
    {
        $user = auth('tenant')->user();
        $tenant = app(TenantManager::class)->tenant();

        if (!$user || !$tenant) {
            $this->totalCount = 0;
            $this->groups = [];
            $this->inbox = [];
            $this->inboxUnread = 0;

            return;
        }

        $summary = app(PendingActionsService::class)->summarize($user, $tenant);
        $this->groups = $summary['groups'] ?? [];

        $this->inbox = [];
        $this->inboxUnread = 0;

        if (
            PressingSettings::channelEnabled('in_app')
            && Schema::connection('tenant')->hasTable('pressing_notifications')
        ) {
            $this->inboxUnread = PressingNotification::query()
                ->where('user_id', $user->id)
                ->unread()
                ->count();

            $this->inbox = PressingNotification::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(12)
                ->get()
                ->map(fn (PressingNotification $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'body' => $n->body,
                    'unread' => $n->read_at === null,
                    'meta' => optional($n->created_at)->diffForHumans(),
                    'url' => $n->data['url'] ?? null,
                ])
                ->all();
        }

        $this->totalCount = (int) ($summary['total'] ?? 0) + $this->inboxUnread;
    }

    public function markRead(string $id): void
    {
        $user = auth('tenant')->user();
        if (!$user || ! Schema::connection('tenant')->hasTable('pressing_notifications')) {
            return;
        }

        $notification = PressingNotification::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        $notification?->markAsRead();
        $this->refreshNotifications();
    }

    public function markAllRead(): void
    {
        $user = auth('tenant')->user();
        if (!$user || ! Schema::connection('tenant')->hasTable('pressing_notifications')) {
            return;
        }

        PressingNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);

        $this->refreshNotifications();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['actions', 'inbox'], true) ? $tab : 'actions';
    }

    public function render()
    {
        return view('livewire.tenant.notification-bell', [
            'tenantCode' => app(TenantManager::class)->tenant()?->code
                ?? request()->query('tenant')
                ?? session('tenant_code'),
            'hasInbox' => count($this->inbox) > 0 || $this->inboxUnread > 0 || PressingSettings::channelEnabled('in_app'),
        ]);
    }
}
