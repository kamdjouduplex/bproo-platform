@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <a class="btn btn-secondary" href="{{ route('tenant.roles.index', ['tenant' => $tenantCode]) }}">{{ __('Rôles') }}</a>
            <a class="btn btn-secondary" href="{{ route('tenant.permissions.index', ['tenant' => $tenantCode]) }}">{{ __('Permissions') }}</a>
            <input class="input input-sm" placeholder="{{ __('Recherche...') }}" wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
        <a class="btn btn-primary" href="{{ route('tenant.users.create', ['tenant' => $tenantCode]) }}">+ {{ __('Nouveau') }}</a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Nom') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Email') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Rôles') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $user->name }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $user->email }}</td>
                        <td class="px-4 py-2.5 text-slate-500">
                            @if ($user->roles->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($user->roles as $role)
                                        <span class="badge badge-secondary">{{ $role->name }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            @if ($user->is_active)
                                <span class="badge badge-success">{{ __('Actif') }}</span>
                            @else
                                <span class="badge badge-warning">{{ __('Inactif') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <a class="table-action table-action-edit"
                               href="{{ route('tenant.users.edit', [$user->id, 'tenant' => $tenantCode]) }}"
                               title="{{ __('Modifier') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun utilisateur.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $users->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </div>
</div>
