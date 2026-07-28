@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <a class="btn btn-secondary" href="{{ route('tenant.users.index', ['tenant' => $tenantCode]) }}">{{ __('Utilisateurs') }}</a>
            <a class="btn btn-secondary" href="{{ route('tenant.permissions.index', ['tenant' => $tenantCode]) }}">{{ __('Permissions') }}</a>
            <input class="input input-sm" placeholder="{{ __('Recherche...') }}" wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
        <a class="btn btn-primary" href="{{ route('tenant.roles.create', ['tenant' => $tenantCode]) }}">+ {{ __('Nouveau') }}</a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Nom') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Description') }}</th>
                        <th class="text-center text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Utilisateurs') }}</th>
                        <th class="text-center text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Permissions') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="role-{{ $role->id }}">
                        <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $role->name }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $role->description ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="badge badge-secondary">{{ $role->users_count }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="badge badge-info">{{ $role->permissions_count }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <a class="table-action table-action-edit"
                               href="{{ route('tenant.roles.edit', [$role->id, 'tenant' => $tenantCode]) }}"
                               title="{{ __('Modifier') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun rôle.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $roles->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </div>
</div>
