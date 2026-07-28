<?php

namespace App\Livewire\Tenant;

use App\Services\PendingActionsService;
use App\Services\TenantManager;
use Livewire\Component;

class NotificationBell extends Component
{
    public int $totalCount = 0;

    /** @var array<int, array<string, mixed>> */
    public array $groups = [];

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

            return;
        }

        $summary = app(PendingActionsService::class)->summarize($user, $tenant);
        $this->totalCount = (int) ($summary['total'] ?? 0);
        $this->groups = $summary['groups'] ?? [];
    }

    public function render()
    {
        return view('livewire.tenant.notification-bell', [
            'tenantCode' => app(TenantManager::class)->tenant()?->code
                ?? request()->query('tenant')
                ?? session('tenant_code'),
        ]);
    }
}
