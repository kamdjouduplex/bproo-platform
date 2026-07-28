<?php

namespace InovCom\Items\Http\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use InovCom\Items\Services\ItemsListColumnService;

trait AuthorizesItemAccess
{
    protected function canItem(string $permission): bool
    {
        return app(ItemsListColumnService::class)->userCan(Auth::guard('tenant')->user(), $permission);
    }

    protected function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
