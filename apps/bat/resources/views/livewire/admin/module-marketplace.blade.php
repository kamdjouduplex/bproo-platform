<div>
    @if (session('success'))
        <div class="flex items-center gap-2 mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="flex items-center gap-2 mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if (session('info'))
        <div class="flex items-center gap-2 mb-4 px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">{{ session('info') }}</div>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 mb-4 px-4 py-3 bg-white border border-slate-200">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <div class="field" style="margin:0;">
                <select class="input" wire:model.live="tenantId" style="min-width:200px;">
                    <option value="">{{ __('Sélectionner une entreprise') }}</option>
                    @foreach ($tenants as $t)
                        <option value="{{ $t['id'] }}">{{ $t['name'] }} ({{ $t['code'] }})</option>
                    @endforeach
                </select>
            </div>
            <input type="search"
                   class="input flex-1"
                   style="max-width:320px;min-width:160px;"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Rechercher un module…') }}"
                   autocomplete="off">
            <button type="button" class="btn btn-secondary" wire:click="syncModulesFromConfig" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Sync modules') }}</span>
                <span wire:loading>{{ __('Synchronisation…') }}</span>
            </button>
        </div>
    </div>

    @if (empty($tenantId))
        <div class="card text-center py-12 text-slate-400 text-sm">
            {{ __('Sélectionnez une entreprise pour installer ou désinstaller des modules.') }}
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse ($modules as $module)
            @php
                $key         = $module['key'];
                $config      = $this->getModuleConfig($key);
                $label       = $config['label'] ?? $module['label'];
                $description = $config['description'] ?? $module['description'] ?? '';
                $isCore      = !empty($config['core']);
                $installed   = $states[$key] ?? false;
                $pending     = $this->isPending($key);
            @endphp
            <article class="flex overflow-hidden border border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm transition-all {{ $installed ? 'border-l-[3px] border-l-emerald-500' : 'border-l-[3px] border-l-blue-500' }}">
                <div class="w-11 flex items-center justify-center shrink-0 {{ $installed ? 'bg-gradient-to-b from-emerald-600 to-emerald-500' : 'bg-gradient-to-b from-blue-700 to-blue-500' }}">
                    <span class="text-lg font-bold text-white">{{ strtoupper(mb_substr($label, 0, 1)) }}</span>
                </div>
                <div class="flex flex-col gap-1.5 p-3 flex-1 min-w-0">
                    <h3 class="text-[13px] font-semibold text-slate-800 leading-snug">{{ $label }}</h3>
                    <p class="text-[11px] text-slate-500 leading-snug line-clamp-2">{{ \Illuminate\Support\Str::limit($description, 80) }}</p>
                    <div class="flex items-center gap-1.5">
                        <span class="font-mono text-[10px] text-slate-400">{{ $key }}</span>
                        @if ($isCore)
                            <span class="badge badge-info text-[10px] py-0 px-1.5">Core</span>
                        @else
                            <span class="badge badge-secondary text-[10px] py-0 px-1.5">Addon</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 mt-1 pt-2 border-t border-slate-100">
                        @if ($pending)
                            <span class="text-[11px] text-slate-400 italic">{{ __('En cours…') }}</span>
                        @elseif ($installed)
                            <span class="badge badge-success text-[10px]">{{ __('Installé') }}</span>
                            <button type="button" class="btn btn-secondary btn-sm text-xs"
                                    wire:click="uninstall('{{ $key }}')" wire:loading.attr="disabled">
                                {{ __('Désinstaller') }}
                            </button>
                        @else
                            <button type="button" class="btn btn-primary btn-sm text-xs"
                                    wire:click="install('{{ $key }}')" wire:loading.attr="disabled">
                                {{ __('Installer') }}
                            </button>
                        @endif
                    </div>
                </div>
            </article>
            @empty
            <div class="col-span-full card text-center py-12 text-slate-400 text-sm">
                @if ($search !== '')
                    {{ __('Aucun module ne correspond à « :q ».', ['q' => $search]) }}
                @else
                    {{ __('Aucun module disponible.') }} Exécutez <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">php artisan modules:sync</code>
                @endif
            </div>
            @endforelse
        </div>
    @endif
</div>
