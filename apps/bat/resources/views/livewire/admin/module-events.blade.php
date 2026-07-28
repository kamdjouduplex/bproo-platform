<div>
    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <input class="input input-sm flex-1 min-w-[140px] max-w-[220px]"
               placeholder="{{ __('Recherche module…') }}"
               wire:model.debounce.400ms="search">
        <select class="input input-sm" wire:model="tenantId" style="min-width:160px;">
            <option value="">{{ __('Toutes les entreprises') }}</option>
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->code }})</option>
            @endforeach
        </select>
        <select class="input input-sm" wire:model="moduleKey" style="min-width:130px;">
            <option value="">{{ __('Tous modules') }}</option>
            @foreach (collect(config('modules'))->except('core_migration_tags')->filter(fn ($m) => is_array($m) && isset($m['label'])) as $key => $module)
                <option value="{{ $key }}">{{ $module['label'] }}</option>
            @endforeach
        </select>
        <select class="input input-sm" wire:model="action" style="min-width:130px;">
            <option value="">{{ __('Toutes actions') }}</option>
            <option value="install">{{ __('Activation') }}</option>
            <option value="uninstall">{{ __('Désactivation') }}</option>
        </select>
        <select class="input input-sm" wire:model="perPage" style="width:70px;">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </select>
        <a class="btn btn-secondary btn-sm" href="{{ route('system.module.events.export', request()->query()) }}">
            {{ __('Exporter') }}
        </a>
    </div>

    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-semibold text-slate-700">{{ __('Historique des modules') }}</span>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('Date') }}</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('Entreprise') }}</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('Module') }}</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('Action') }}</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('Par') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 text-slate-400 text-xs whitespace-nowrap">{{ $event->created_at }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $event->tenant?->name ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-indigo-600">{{ $event->module_key }}</td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $event->action === 'install' ? 'badge-success' : 'badge-warning' }}">{{ $event->action }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ $event->performer?->email ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun événement.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $events->links('livewire.admin.module-events-pagination') }}
        </div>
    </div>
</div>
