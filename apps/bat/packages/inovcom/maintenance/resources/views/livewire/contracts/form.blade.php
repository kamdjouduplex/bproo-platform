@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $isExpired  = $status === 'expired';
    $statusBg = match($status) {
        'active'    => 'bg-emerald-100 text-emerald-700',
        'suspended' => 'bg-amber-100 text-amber-700',
        default     => 'bg-slate-100 text-slate-600',
    };
    $statusLabel = match($status) {
        'active'    => __('Actif'),
        'suspended' => __('Suspendu'),
        default     => __('Expiré'),
    };
@endphp
<div>
    @include('inovcom-maintenance::livewire._tabs')

    {{-- ── Action bar (edit mode) ─────────────────────────────────────────── --}}
    @if($contractId)
    <div class="flex items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl px-4 py-3 mb-4">
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $statusBg }}">{{ $statusLabel }}</span>
            <span class="font-mono text-[12px] font-semibold text-slate-500">{{ $code }}</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(in_array('suspended', $allowedTransitions))
                <button type="button" class="btn btn-secondary btn-sm" wire:click="suspendContract"
                        wire:loading.attr="disabled" wire:confirm="{{ __('Suspendre ce contrat ?') }}">
                    {{ __('Suspendre') }}
                </button>
            @endif
            @if(in_array('active', $allowedTransitions))
                <button type="button" class="btn btn-primary btn-sm" wire:click="reactivateContract"
                        wire:loading.attr="disabled" wire:confirm="{{ __('Réactiver ce contrat ?') }}">
                    {{ __('Réactiver') }}
                </button>
            @endif
            @if(in_array('expired', $allowedTransitions))
                <button type="button" class="btn btn-secondary btn-sm" wire:click="expireContract"
                        wire:loading.attr="disabled" wire:confirm="{{ __('Marquer ce contrat comme expiré ?') }}">
                    {{ __('Expirer') }}
                </button>
            @endif
        </div>
    </div>
    @endif

    <form wire:submit="save" autocomplete="off">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

            {{-- ── Informations générales ─────────────────────────────── --}}
            <section class="card">
                <h3 class="text-[13px] font-semibold text-slate-700 mb-3">{{ __('Informations générales') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="field">
                        <label class="field-label">{{ __('Client') }} <span class="text-red-500">*</span></label>
                        <select class="input" wire:model="client_id" @if($isExpired) disabled @endif>
                            <option value="0">{{ __('-- Sélectionner --') }}</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                        @error('client_id') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Titre / Référence') }}</label>
                        <input type="text" class="input" wire:model="title" @if($isExpired) disabled @endif>
                        @error('title') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Type') }} <span class="text-red-500">*</span></label>
                        <select class="input" wire:model="type" @if($isExpired) disabled @endif>
                            <option value="preventive">{{ __('Préventif') }}</option>
                            <option value="corrective">{{ __('Correctif') }}</option>
                            <option value="full_service">{{ __('Tous services') }}</option>
                        </select>
                        @error('type') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Cycle de facturation') }}</label>
                        <select class="input" wire:model="billing_cycle" @if($isExpired) disabled @endif>
                            @foreach($billingCycles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Périodicité de facturation au client.') }}</span>
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Fréquence des visites') }} <span class="text-red-500">*</span></label>
                        <select class="input" wire:model="intervention_frequency" @if($isExpired) disabled @endif>
                            @foreach($interventionFrequencies as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Planification des interventions préventives.') }}</span>
                        @error('intervention_frequency') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Date de début') }} <span class="text-red-500">*</span></label>
                        <input type="date" class="input" wire:model="start_date" @if($isExpired) disabled @endif>
                        @error('start_date') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Date de fin') }}</label>
                        <input type="date" class="input" wire:model="end_date" @if($isExpired) disabled @endif>
                        @error('end_date') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Prix mensuel (HT)') }}</label>
                        <input type="number" step="0.01" class="input" wire:model="price_per_month" @if($isExpired) disabled @endif>
                        @error('price_per_month') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </section>

            {{-- ── SLA ──────────────────────────────────────────────────── --}}
            <section class="card">
                <h3 class="text-[13px] font-semibold text-slate-700 mb-3">{{ __('Niveaux de service (SLA)') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="field">
                        <label class="field-label">{{ __('Délai de réponse (heures)') }}</label>
                        <input type="number" min="1" class="input" wire:model="response_time" placeholder="ex: 4" @if($isExpired) disabled @endif>
                        @error('response_time') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Délai de résolution (heures)') }}</label>
                        <input type="number" min="1" class="input" wire:model="resolution_time" placeholder="ex: 24" @if($isExpired) disabled @endif>
                        @error('resolution_time') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field sm:col-span-2">
                        <label class="field-label">{{ __('Sites couverts (JSON)') }}</label>
                        <textarea class="input" rows="4" wire:model="sites_json" placeholder='[{"label":"Site A","address":"123 rue..."}]' @if($isExpired) disabled @endif></textarea>
                        <span class="text-[11px] text-slate-400 mt-0.5">{{ __("Format JSON : tableau d'objets {label, address}") }}</span>
                    </div>

                    <div class="field sm:col-span-2">
                        <label class="field-label">{{ __('Conditions générales') }}</label>
                        <textarea class="input" rows="5" wire:model="terms" @if($isExpired) disabled @endif></textarea>
                    </div>
                </div>
            </section>

        </div>

        {{-- ── Cycle de maintenance (edit) ─────────────────────────────── --}}
        @if($contractId && $cycleSummary)
        <section class="card mb-4">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-[13px] font-semibold text-slate-700">{{ __('Cycle de gestion maintenance') }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('Visites :frequency — Facturation :billing', ['frequency' => $cycleSummary['frequency_label'], 'billing' => $cycleSummary['billing_label']]) }}</p>
                </div>
                @if(!$isExpired && in_array($type, ['preventive', 'full_service']))
                <button type="button" class="btn btn-primary btn-sm" wire:click="planNextIntervention"
                        wire:loading.attr="disabled" wire:target="planNextIntervention">
                    {{ __('Planifier la prochaine visite') }}
                </button>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4 text-sm">
                <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('Dernière visite') }}</p>
                    <p class="font-semibold text-slate-800 mt-0.5">{{ $cycleSummary['last_intervention'] ?? '—' }}</p>
                </div>
                <div class="rounded-lg border px-3 py-2.5 {{ $cycleSummary['is_overdue'] ? 'bg-amber-50 border-amber-200' : 'bg-emerald-50 border-emerald-100' }}">
                    <p class="text-[10px] uppercase tracking-wide {{ $cycleSummary['is_overdue'] ? 'text-amber-700' : 'text-emerald-600' }} font-semibold">{{ __('Prochaine visite') }}</p>
                    <p class="font-semibold {{ $cycleSummary['is_overdue'] ? 'text-amber-900' : 'text-emerald-800' }} mt-0.5">{{ $cycleSummary['next_intervention'] ?? '—' }}</p>
                    @if($cycleSummary['is_overdue'])
                    <p class="text-[10px] text-amber-700 mt-0.5">{{ __('Visite en retard') }}</p>
                    @endif
                </div>
                <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('Ordres ouverts') }}</p>
                    <p class="font-semibold text-slate-800 mt-0.5">{{ $cycleSummary['open_orders_count'] }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('Génération auto') }}</p>
                    <p class="font-semibold text-slate-800 mt-0.5">{{ $auto_generate_orders ? __('Activée') : __('Désactivée') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div class="field">
                    <label class="field-label">{{ __('Prochaine intervention') }}</label>
                    <input type="date" class="input" wire:model="next_intervention_at" @if($isExpired) disabled @endif>
                </div>
                <div class="field">
                    <label class="field-label">{{ __('Dernière intervention') }}</label>
                    <input type="date" class="input" wire:model="last_intervention_at" @if($isExpired) disabled @endif>
                </div>
                <div class="field flex items-end">
                    <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer pb-2.5">
                        <input type="checkbox" class="rounded border-slate-300" wire:model="auto_generate_orders" @if($isExpired) disabled @endif>
                        {{ __('Créer les ordres automatiquement') }}
                    </label>
                </div>
            </div>

            @if(count($cycleSummary['recent_orders']) > 0)
            <h4 class="text-xs font-semibold text-slate-600 mb-2">{{ __('Interventions récentes') }}</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-slate-500 border-b border-slate-100">
                            <th class="text-left font-semibold py-1.5 pr-3">{{ __('Ordre') }}</th>
                            <th class="text-left font-semibold py-1.5 pr-3">{{ __('Type') }}</th>
                            <th class="text-left font-semibold py-1.5 pr-3">{{ __('Statut') }}</th>
                            <th class="text-left font-semibold py-1.5">{{ __('Signalé le') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cycleSummary['recent_orders'] as $order)
                        <tr class="border-b border-slate-50" wire:key="cycle-order-{{ $order['id'] }}">
                            <td class="py-2 pr-3">
                                <a class="font-mono text-indigo-600 hover:underline" href="{{ route('tenant.maintenance.orders.edit', ['tenant' => $tenantCode, 'maintenance_order' => $order['id']]) }}">{{ $order['code'] }}</a>
                            </td>
                            <td class="py-2 pr-3 text-slate-600">{{ $order['type'] }}</td>
                            <td class="py-2 pr-3 text-slate-600">{{ $order['status'] }}</td>
                            <td class="py-2 text-slate-500">{{ $order['reported_at'] ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>
        @endif

        {{-- ── Documents ─────────────────────────────────────────────── --}}
        <section class="card mb-4">
            @if($contractId && class_exists(\InovCom\Dms\Http\Livewire\EntityDocuments::class))
                <livewire:inovcom-dms.entity-documents
                    attachable-type="maintenance_contract"
                    :attachable-id="$contractId"
                    :key="'contract-docs-'.$contractId"
                />
            @else
                <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 border border-dashed border-slate-200 rounded-xl text-[13px] text-slate-400">
                    {{ __('Enregistrez le contrat pour y attacher des documents (contrat signé, SLA, plans…).') }}
                </div>
            @endif
        </section>

        @if(!$isExpired)
        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-slate-100">
            <a href="{{ route('tenant.maintenance.contracts.index', ['tenant' => $tenantCode]) }}" class="btn btn-secondary">{{ __('Annuler') }}</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
            </button>
        </div>
        @endif
    </form>
</div>
