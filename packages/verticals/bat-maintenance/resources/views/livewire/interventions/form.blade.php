@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $isDone     = $status === 'done';
    $statusBg = match($status) {
        'scheduled'   => 'bg-blue-100 text-blue-600',
        'in_progress' => 'bg-indigo-100 text-indigo-700',
        default       => 'bg-emerald-100 text-emerald-700',
    };
    $statusLabel = match($status) {
        'scheduled'   => __('Planifiée'),
        'in_progress' => __('En cours'),
        default       => __('Terminée'),
    };
@endphp
<div>
    @include('inovcom-maintenance::livewire._tabs')

    {{-- ── Context banner ──────────────────────────────────────────────────── --}}
    @if($orderCode)
    <div class="flex items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl px-4 py-3 mb-4">
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $statusBg }}">{{ $statusLabel }}</span>
            <span class="font-mono text-[12px] font-semibold text-slate-500">{{ $orderCode }}</span>
            <span class="text-[13px] text-slate-500">{{ $orderTitle }}</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(in_array('in_progress', $allowedTransitions))
                <button type="button" class="btn btn-primary btn-sm" wire:click="startIntervention"
                        wire:loading.attr="disabled" wire:confirm="{{ __('Démarrer cette intervention ?') }}">
                    {{ __('Démarrer') }}
                </button>
            @endif
            @if(in_array('done', $allowedTransitions))
                <button type="button" class="btn btn-success btn-sm" wire:click="completeIntervention"
                        wire:loading.attr="disabled" wire:confirm="{{ __('Marquer comme terminée ?') }}">
                    {{ __('Terminer') }}
                </button>
            @endif
        </div>
    </div>
    @endif

    <form wire:submit="save" autocomplete="off">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

            {{-- ── Planification ────────────────────────────────────────── --}}
            <section class="card">
                <h3 class="text-[13px] font-semibold text-slate-700 mb-3">{{ __('Planification') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div class="field">
                        <label class="field-label">{{ __('Technicien') }} <span class="text-red-500">*</span></label>
                        <select class="input" wire:model="technician_id" @if($isDone) disabled @endif>
                            <option value="0">{{ __('-- Sélectionner --') }}</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('technician_id') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Planifiée le') }} <span class="text-red-500">*</span></label>
                        <input type="datetime-local" class="input" wire:model="scheduled_at" @if($isDone) disabled @endif>
                        @error('scheduled_at') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Démarrée le') }}</label>
                        <input type="datetime-local" class="input" wire:model="started_at" @if($isDone) disabled @endif>
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Terminée le') }}</label>
                        <input type="datetime-local" class="input" wire:model="completed_at" @if($isDone) disabled @endif>
                        @error('completed_at') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Durée (minutes)') }}</label>
                        <input type="number" min="0" class="input" wire:model="duration_minutes" placeholder="{{ __('Auto calculé si vide') }}" @if($isDone) disabled @endif>
                    </div>

                </div>
            </section>

            {{-- ── Rapport ──────────────────────────────────────────────── --}}
            <section class="card">
                <h3 class="text-[13px] font-semibold text-slate-700 mb-3">{{ __("Rapport d'intervention") }}</h3>
                <div class="grid grid-cols-1 gap-4">

                    <div class="field">
                        <label class="field-label">{{ __('Travaux réalisés') }}</label>
                        <textarea class="input" rows="4" wire:model="work_done" @if($isDone) disabled @endif></textarea>
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Observations / Constatations') }}</label>
                        <textarea class="input" rows="3" wire:model="findings" @if($isDone) disabled @endif></textarea>
                    </div>

                    <div class="field">
                        <label class="field-label">{{ __('Prochaine action recommandée') }}</label>
                        <textarea class="input" rows="2" wire:model="next_action" @if($isDone) disabled @endif></textarea>
                    </div>

                </div>
            </section>

            {{-- ── Matériaux utilisés ───────────────────────────────────── --}}
            <section class="card lg:col-span-2">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-[13px] font-semibold text-slate-700">{{ __('Matériaux / Pièces utilisés') }}</h3>
                    @if(!$isDone)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addMaterialRow">
                        + {{ __('Ligne') }}
                    </button>
                    @endif
                </div>

                @if(count($materials) > 0)
                <div class="overflow-x-auto border border-slate-200 rounded-lg">
                    <table class="w-full border-collapse text-[12px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="text-left text-slate-500 font-semibold text-[11px] px-3 py-2">{{ __('Description') }}</th>
                                <th class="text-left text-slate-500 font-semibold text-[11px] px-3 py-2 w-20">{{ __('Qté') }}</th>
                                <th class="text-left text-slate-500 font-semibold text-[11px] px-3 py-2 w-28">{{ __('Prix unitaire') }}</th>
                                <th class="text-right text-slate-500 font-semibold text-[11px] px-3 py-2 w-24">{{ __('Total') }}</th>
                                @if(!$isDone) <th class="w-9"></th> @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materials as $i => $material)
                            <tr class="border-b border-slate-100" wire:key="material-{{ $i }}">
                                <td class="px-3 py-1.5">
                                    <input type="text" class="input input-sm w-full" wire:model="materials.{{ $i }}.description" @if($isDone) disabled @endif>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="number" step="0.01" min="0" class="input input-sm w-full" wire:model="materials.{{ $i }}.qty" @if($isDone) disabled @endif>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input type="number" step="0.01" min="0" class="input input-sm w-full" wire:model="materials.{{ $i }}.unit_price" @if($isDone) disabled @endif>
                                </td>
                                <td class="px-3 py-1.5 text-right text-slate-700 font-medium">
                                    {{ number_format(($material['qty'] ?? 0) * ($material['unit_price'] ?? 0), 2) }}
                                </td>
                                @if(!$isDone)
                                <td class="px-2 py-1.5 text-center">
                                    <button type="button" class="table-action table-action-delete" wire:click="removeMaterialRow({{ $i }})">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50">
                                <td colspan="3" class="px-3 py-2 text-right text-[12px] font-semibold text-slate-600">{{ __('Total matériaux') }}</td>
                                <td class="px-3 py-2 text-right text-[13px] font-bold text-slate-900">{{ number_format($materialsCost, 2) }}</td>
                                @if(!$isDone) <td></td> @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <p class="text-[13px] text-slate-400 py-3">{{ __('Aucun matériau enregistré.') }}</p>
                @endif
            </section>

            {{-- ── Signature client ─────────────────────────────────────── --}}
            <section class="card">
                <h3 class="text-[13px] font-semibold text-slate-700 mb-3">{{ __('Signature client') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="field">
                        <label class="field-label">{{ __('Date de signature') }}</label>
                        <input type="datetime-local" class="input" wire:model="signed_at" @if($isDone) disabled @endif>
                    </div>
                    <div class="field sm:col-span-2">
                        <label class="field-label">{{ __('Signature (base64)') }}</label>
                        @if($client_signature)
                            <img src="{{ $client_signature }}" alt="{{ __('Signature') }}" class="max-h-20 border border-slate-200 rounded-lg p-1">
                        @else
                            <span class="text-[13px] text-slate-400">{{ __('Aucune signature.') }}</span>
                        @endif
                    </div>
                </div>
            </section>

        </div>

        @if(!$isDone)
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
