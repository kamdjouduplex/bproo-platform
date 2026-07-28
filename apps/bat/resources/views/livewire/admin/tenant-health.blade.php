<div>
    <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-semibold text-slate-700">{{ __('Statut des bases entreprises') }}</span>
        <button class="btn btn-secondary" wire:click="refreshStatuses">{{ __('Rafraîchir') }}</button>
    </div>
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('Entreprise') }}</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('Code') }}</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('DB') }}</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">{{ __('Message') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($statuses as $status)
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $status['name'] }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-indigo-600">{{ $status['code'] }}</td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $status['db_name'] }}</td>
                        <td class="px-4 py-3">
                            @if ($status['status'] === 'ok')
                                <span class="badge badge-success">OK</span>
                            @else
                                <span class="badge badge-danger">Erreur</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ $status['message'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucune entreprise.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
