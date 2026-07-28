<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-slate-700">{{ __('Liste des entreprises') }}</h2>
        <a class="btn btn-primary" href="{{ route('system.tenants.create') }}">+ {{ __('Nouveau') }}</a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Nom</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Code</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">DB</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Plan</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Statut</th>
                        <th class="text-left text-slate-500 font-semibold text-xs uppercase tracking-wide px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenants as $tenant)
                    @php
                        $planKey   = $tenant['plan'] ?? 'free';
                        $planLabel = match($planKey) { 'starter'=>'Starter','pro'=>'Pro','enterprise'=>'Enterprise',default=>'Gratuit' };
                        $planClass = match($planKey) {
                            'starter'    => 'bg-blue-50 text-blue-700',
                            'pro'        => 'bg-violet-50 text-violet-700',
                            'enterprise' => 'bg-fuchsia-50 text-fuchsia-700',
                            default      => 'bg-slate-100 text-slate-500',
                        };
                    @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $tenant['name'] }}</td>
                        <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ $tenant['code'] }}</td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $tenant['db_name'] }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold {{ $planClass }}">{{ $planLabel }}</span>
                            @if (!empty($tenant['plan_expires_at']) && \Carbon\Carbon::parse($tenant['plan_expires_at'])->isPast())
                                <span class="block text-[10px] text-red-500 mt-0.5">expiré</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($tenant['is_active'])
                                <span class="badge badge-success">Actif</span>
                            @else
                                <span class="badge badge-warning">Inactif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.edit', $tenant['code']) }}">Modifier</a>
                                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.settings', $tenant['code']) }}">Paramètres</a>
                                <button class="btn btn-danger btn-sm" wire:click="delete({{ $tenant['id'] }})"
                                        wire:confirm="{{ __('Supprimer cette entreprise ?') }}">
                                    Supprimer
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
