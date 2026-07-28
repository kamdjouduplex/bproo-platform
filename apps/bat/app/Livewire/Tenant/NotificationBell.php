<?php

namespace App\Livewire\Tenant;

use Livewire\Component;

/**
 * Notification bell displayed in the tenant topbar.
 *
 * Reads from Laravel's `notifications` table (tenant DB) for the
 * currently authenticated tenant user.
 *
 * Polls every 60 seconds via wire:poll to refresh the unread count.
 */
class NotificationBell extends Component
{
    public bool $open = false;

    public function markAllRead(): void
    {
        $user = auth('tenant')->user();
        if ($user) {
            $user->unreadNotifications()->update(['read_at' => now()]);
        }
        $this->open = false;
    }

    public function markRead(string $id): void
    {
        $user = auth('tenant')->user();
        if ($user) {
            $user->notifications()->where('id', $id)->update(['read_at' => now()]);
        }
    }

    public function toggle(): void
    {
        $this->open = !$this->open;
    }

    public function render()
    {
        $user          = auth('tenant')->user();
        $notifications = collect();
        $unreadCount   = 0;

        if ($user) {
            $notifications = $user->notifications()->latest()->limit(15)->get();
            $unreadCount   = $user->unreadNotifications()->count();
        }

        return view('livewire.tenant.notification-bell', [
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
        ]);
    }
}
