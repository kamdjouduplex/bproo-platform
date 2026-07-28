<div class="flex flex-col gap-4">
    <div class="card">
        <div class="field">
            <label class="field-label" for="tenant-select">{{ __('Entreprise') }}</label>
            <select id="tenant-select" class="input" wire:model.live="tenantId" style="max-width:320px;">
                <option value="">{{ __('Sélectionner une entreprise') }}</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant['id'] }}">{{ $tenant['name'] }} ({{ $tenant['code'] }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
            <span class="text-sm font-semibold text-slate-700">{{ __('Modules disponibles') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Module</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Description</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Statut</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Mise à jour</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modules as $module)
                    @php $enabled = $states[$module['id']] ?? false; @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $module['label'] }}</td>
                        <td class="px-4 py-3 text-slate-500 text-xs">{{ $module['description'] }}</td>
                        <td class="px-4 py-3">
                            @if ($enabled)
                                <span class="badge badge-success">Actif</span>
                            @else
                                <span class="badge badge-warning">Désactivé</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $updatedAt[$module['id']] ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <button class="btn btn-secondary btn-sm" wire:click="toggle({{ $module['id'] }})">
                                {{ $enabled ? __('Désactiver') : __('Activer') }}
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
