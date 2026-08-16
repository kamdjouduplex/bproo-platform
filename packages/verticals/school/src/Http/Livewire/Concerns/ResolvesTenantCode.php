<?php

namespace School\Http\Livewire\Concerns;

trait ResolvesTenantCode
{
    protected function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? request()->attributes->get('tenant')?->code
            ?? (app()->bound('tenant') ? app('tenant')?->code : null);
    }

    protected function tenantRoute(string $name, array $params = []): string
    {
        return route($name, array_merge(['tenant' => $this->tenantCode()], $params));
    }
}
