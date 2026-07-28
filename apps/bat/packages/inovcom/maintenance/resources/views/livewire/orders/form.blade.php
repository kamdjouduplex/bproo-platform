@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $isClosed   = in_array($status, ['closed', 'cancelled']);
    $statusBg = match($status) {
        'open'        => 'bg-slate-100 text-slate-600',
        'assigned'    => 'bg-blue-100 text-blue-600',
        'in_progress' => 'bg-indigo-100 text-indigo-700',
        'done'        => 'bg-emerald-100 text-emerald-700',
        'closed'      => 'bg-slate-100 text-slate-500',
        default       => 'bg-red-100 text-red-700',
    };
    $statusLabel = match($status) {
        'open'        => __('Ouvert'),
        'assigned'    => __('Assigné'),
        'in_progress' => __('En cours'),
        'done'        => __('Terminé'),
        'closed'      => __('Clôturé'),
        default       => __('Annulé'),
    };
@endphp
<div>
    @include('inovcom-maintenance::livewire._tabs')

    {{-- ── Action bar ──────────────────────────────────────────────────────── --}}
    @if($orderId)
    <div class="flex items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl px-4 py-3 mb-4">
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $statusBg }}">{{ $statusLabel }}</span>
            <span class="font-mono text-[12px] font-semibold text-slate-500">{{ $code }}</span>

            @if($slaBreached)
                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-700">{{ __('SLA dépassé') }}</span>
            @elseif($slaHours !== null)
                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700">{{ __('SLA :') }} {{ (int)$slaHours }}h {{ __('restantes') }}</span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(in_array('assigned', $allowedTransitions))
                <button type="button" class="btn btn-primary btn-sm" wire:click="assignOrder"
                        wire:loading.attr="disabled" wire:confirm="{{ __('Assigner cet ordre ?') }}">
                    {{ __('Assigner') }}
                </button>
            @endif
            @if(in_array('in_progress', $allowedTransitions))
                <button type="button" class="btn btn-primary btn-sm" wire:click="startOrder"
                        wire:loading.attr="disabled" wire:confirm="{{ __("Démarrer l'intervention ?") }}">
                    {{ __('Démarrer') }}
                </button>
            @endif
            @if(in_array('closed', $allowedTransitions))
                <button type="button" class="btn btn-success btn-sm" wire:click="closeOrder"
                        wire:loading.attr="disabled" wire:confirm="{{ __('Clôturer cet ordre ?') }}">
                    {{ __('Clôturer') }}
                </button>
            @endif
            @if(in_array('cancelled', $allowedTransitions))
                <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelOrder"
                        wire:loading.attr="disabled" wire:confirm="{{ __("Annuler cet ordre de maintenance ?") }}">
                    {{ __('Annuler') }}
                </button>
            @endif
            @if($orderId)
                <a class="btn btn-secondary btn-sm"
                   href="{{ route('tenant.maintenance.interventions.create', ['tenant' => $tenantCode, 'maintenance_order' => $orderId]) }}">
                    + {{ __('Intervention') }}
                </a>
            @endif
        </div>
    </div>
    @endif

    <form wire:submit="save" autocomplete="off">
        <section class="card mb-4">
            <h3 class="text-[13px] font-semibold text-slate-700 mb-3">{{ __("Détails de l'ordre") }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="field">
                    <label class="field-label">{{ __('Contrat lié') }}</label>
                    <select class="input" wire:model.live="contract_id" @if($isClosed) disabled @endif>
                        <option value="0">{{ __('-- Sans contrat --') }}</option>
                        @foreach($contracts as $contract)
                            <option value="{{ $contract->id }}">{{ $contract->code }} — {{ $contract->title ?? $contract->code }}</option>
                        @endforeach
                    </select>
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('La sélection auto-remplit le client et le SLA.') }}</span>
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Client') }} <span class="text-red-500">*</span></label>
                    <select class="input" wire:model="client_id" @if($isClosed) disabled @endif>
                        <option value="0">{{ __('-- Sélectionner --') }}</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @error('client_id') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="field sm:col-span-2">
                    <label class="field-label">{{ __('Titre') }} <span class="text-red-500">*</span></label>
                    <input type="text" class="input" wire:model="title" @if($isClosed) disabled @endif>
                    @error('title') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="field sm:col-span-2">
                    <label class="field-label">{{ __('Description') }}</label>
                    <textarea class="input" rows="3" wire:model="description" @if($isClosed) disabled @endif></textarea>
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Type') }}</label>
                    <select class="input" wire:model="type" @if($isClosed) disabled @endif>
                        <option value="corrective">{{ __('Correctif') }}</option>
                        <option value="preventive">{{ __('Préventif') }}</option>
                        <option value="emergency">{{ __('Urgence') }}</option>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Priorité') }}</label>
                    <select class="input" wire:model="priority" @if($isClosed) disabled @endif>
                        <option value="low">{{ __('Basse') }}</option>
                        <option value="normal">{{ __('Normale') }}</option>
                        <option value="high">{{ __('Haute') }}</option>
                        <option value="critical">{{ __('Critique') }}</option>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Assigné à') }}</label>
                    <select class="input" wire:model="assigned_to" @if($isClosed) disabled @endif>
                        <option value="">{{ __('-- Non assigné --') }}</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Signalé par') }}</label>
                    <input type="text" class="input" wire:model="reported_by" @if($isClosed) disabled @endif>
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Date / heure signalement') }} <span class="text-red-500">*</span></label>
                    <input type="datetime-local" class="input" wire:model="reported_at" @if($isClosed) disabled @endif>
                    @error('reported_at') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Échéance SLA') }}</label>
                    <input type="datetime-local" class="input" wire:model="due_at" @if($isClosed) disabled @endif>
                    @error('due_at') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="field sm:col-span-2">
                    <label class="field-label">{{ __('Adresse du site') }}</label>
                    <textarea class="input" rows="2" wire:model="site_address" @if($isClosed) disabled @endif></textarea>
                </div>

            </div>
        </section>

        {{-- ── Documents panel (only in edit mode) ─────────────────── --}}
        @if($orderId)
        <section class="card mb-4">
            <livewire:inovcom-dms.entity-documents
                attachable-type="maintenance_order"
                :attachable-id="$orderId"
                :key="'order-docs-'.$orderId"
            />
        </section>
        @endif

        @if(!$isClosed)
        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-slate-100">
            <a href="{{ route('tenant.maintenance.orders.index', ['tenant' => $tenantCode]) }}" class="btn btn-secondary">{{ __('Annuler') }}</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
            </button>
        </div>
        @endif
    </form>
</div>
