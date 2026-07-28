<div>
    <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-semibold text-slate-700">{{ __('Catalogue des modules') }}</span>
        <a class="btn btn-primary" href="{{ route('system.modules.create') }}">+ {{ __('Nouveau') }}</a>
    </div>
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Clé</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Module</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Description</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Route</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Par défaut</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-mono text-xs text-indigo-600">{{ $module['key'] }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $module['label'] }}</td>
                        <td class="px-4 py-3 text-slate-500 text-xs max-w-[200px] truncate">{{ $module['description'] }}</td>
                        <td class="px-4 py-3 text-slate-400 font-mono text-xs">{{ $module['route_name'] }}</td>
                        <td class="px-4 py-3">
                            @if ($module['enabled_by_default'])
                                <span class="badge badge-success">Actif</span>
                            @else
                                <span class="badge badge-warning">Inactif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <a class="btn btn-secondary btn-sm" href="{{ route('system.modules.edit', $module['id']) }}">Modifier</a>
                                <button class="btn btn-danger btn-sm" wire:click="delete({{ $module['id'] }})"
                                        wire:confirm="{{ __('Supprimer ce module ?') }}">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucun module pour le moment.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
