@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $isLocked   = in_array($status, ['received', 'cancelled']);
    $statusBg   = match($status) {
        'draft'              => 'bg-slate-100 text-slate-600',
        'pending_validation' => 'bg-amber-100 text-amber-700',
        'validated'          => 'bg-blue-100 text-blue-700',
        'ordered'            => 'bg-violet-100 text-violet-700',
        'received'           => 'bg-emerald-100 text-emerald-700',
        'partially_received' => 'bg-teal-100 text-teal-700',
        'cancelled'          => 'bg-red-100 text-red-700',
        default              => 'bg-slate-100 text-slate-500',
    };
    $statusLabel = match($status) {
        'draft'              => __('Brouillon'),
        'pending_validation' => __('En attente de validation'),
        'validated'          => __('Validé'),
        'ordered'            => __('Commandé'),
        'received'           => __('Reçu'),
        'partially_received' => __('Partiellement reçu'),
        'cancelled'          => __('Annulé'),
        default              => $status,
    };
@endphp
<div>
    {{-- ── Action bar (edit mode only) ────────────────────────────────────── --}}
    @if($purchaseOrderId)
    <div class="flex items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl px-4 py-3 mb-4">
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold uppercase tracking-wide {{ $statusBg }}">
                {{ $statusLabel }}
            </span>
            <span class="font-mono text-[12px] font-semibold text-slate-500">{{ $code }}</span>
            @if($liveTotal > 0)
                <span class="text-sm font-bold text-slate-800">{{ number_format($liveTotal, 0, ',', ' ') }} XOF</span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(in_array('pending_validation', $allowedTransitions))
                <button type="button" class="btn btn-primary btn-sm"
                        wire:click="submitForValidation"
                        wire:loading.attr="disabled" wire:target="submitForValidation"
                        wire:confirm="{{ __('Soumettre ce bon de commande pour validation ?') }}">
                    {{ __('Soumettre') }}
                </button>
            @endif
            @if(in_array('draft', $allowedTransitions))
                <button type="button" class="btn btn-secondary btn-sm"
                        wire:click="backToDraft"
                        wire:loading.attr="disabled" wire:target="backToDraft"
                        wire:confirm="{{ __('Remettre en brouillon ?') }}">
                    {{ __('Brouillon') }}
                </button>
            @endif
            @if(in_array('validated', $allowedTransitions))
                <button type="button" class="btn btn-success btn-sm"
                        wire:click="approvePO"
                        wire:loading.attr="disabled" wire:target="approvePO"
                        wire:confirm="{{ __('Valider ce bon de commande ?') }}">
                    {{ __('Valider') }}
                </button>
            @endif
            @if(in_array('ordered', $allowedTransitions))
                <button type="button" class="btn btn-primary btn-sm"
                        wire:click="markAsOrdered"
                        wire:loading.attr="disabled" wire:target="markAsOrdered"
                        wire:confirm="{{ __('Marquer comme commandé ?') }}">
                    {{ __('Commandé') }}
                </button>
            @endif
            @if(in_array('partially_received', $allowedTransitions))
                <button type="button" class="btn btn-secondary btn-sm"
                        wire:click="markAsPartiallyReceived"
                        wire:loading.attr="disabled" wire:target="markAsPartiallyReceived"
                        wire:confirm="{{ __('Marquer comme partiellement reçu ?') }}">
                    {{ __('Reçu (partiel)') }}
                </button>
            @endif
            @if(in_array('received', $allowedTransitions))
                <button type="button" class="btn btn-success btn-sm"
                        wire:click="markAsReceived"
                        wire:loading.attr="disabled" wire:target="markAsReceived"
                        wire:confirm="{{ __('Marquer comme entièrement reçu ?') }}">
                    {{ __('Reçu') }}
                </button>
            @endif
            @if(in_array('cancelled', $allowedTransitions))
                <button type="button" class="btn btn-danger btn-sm"
                        wire:click="cancelPO"
                        wire:loading.attr="disabled" wire:target="cancelPO"
                        wire:confirm="{{ __('Annuler définitivement ce bon de commande ?') }}">
                    {{ __('Annuler') }}
                </button>
            @endif
        </div>
    </div>
    @endif

    <section class="card">
        <h2 class="text-base font-semibold text-slate-800 mb-5">
            {{ $purchaseOrderId ? __('Modifier le bon de commande') : __('Nouveau bon de commande') }}
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Code --}}
            @if($purchaseOrderId)
                <div class="field">
                    <label class="field-label">{{ __('Code') }}</label>
                    <input class="input bg-slate-50 text-slate-500" wire:model="code" readonly disabled>
                </div>
            @else
                <div class="field">
                    <label class="field-label">{{ __('Code') }}</label>
                    <input class="input bg-slate-50 text-slate-400" value="—" readonly disabled>
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Généré automatiquement (ex. BON00001)') }}</span>
                </div>
            @endif

            {{-- Supplier --}}
            <div class="field">
                <label class="field-label">{{ __('Fournisseur') }} <span class="text-red-500">*</span></label>
                <select class="input" wire:model="supplier_id" @disabled($isLocked)>
                    <option value="0">— {{ __('Choisir un fournisseur') }} —</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</option>
                    @endforeach
                </select>
                @error('supplier_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Project --}}
            <div class="field">
                <label class="field-label">{{ __('Projet (optionnel)') }}</label>
                <select class="input" wire:model="project_id" @disabled($isLocked)>
                    <option value="">— {{ __('Aucun') }} —</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} – {{ \Illuminate\Support\Str::limit($p->title, 35) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Ordered at --}}
            <div class="field">
                <label class="field-label">{{ __('Date de commande') }}</label>
                <input class="input" wire:model="ordered_at" type="date" @disabled($isLocked)>
                @error('ordered_at') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Received at --}}
            <div class="field">
                <label class="field-label">{{ __('Date de réception') }}</label>
                <input class="input" wire:model="received_at" type="date" @disabled($isLocked)>
                @error('received_at') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Notes --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Notes / Conditions') }}</label>
                <textarea class="input" wire:model="notes" rows="2"
                          @disabled($isLocked)
                          placeholder="{{ __('Conditions de paiement, délais de livraison, remarques...') }}"></textarea>
            </div>
        </div>

        {{-- ── Line items ───────────────────────────────────────────────────── --}}
        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[13px] font-semibold text-slate-700">{{ __('Lignes du bon de commande') }}</span>
                @if(!$isLocked)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addLine">
                        + {{ __('Ajouter une ligne') }}
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-lg">
                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="text-left text-slate-500 font-semibold text-[11px] px-3 py-2">{{ __('Désignation') }}</th>
                            <th class="text-left text-slate-500 font-semibold text-[11px] px-3 py-2 w-20">{{ __('Qté') }}</th>
                            <th class="text-left text-slate-500 font-semibold text-[11px] px-3 py-2 w-28">{{ __('P.U. HT') }}</th>
                            <th class="text-right text-slate-500 font-semibold text-[11px] px-3 py-2 w-28">{{ __('Montant HT') }}</th>
                            @if(!$isLocked)<th class="w-9"></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $index => $line)
                            <tr class="border-b border-slate-100" wire:key="line-{{ $index }}">
                                <td class="px-3 py-1.5">
                                    <input class="input input-sm w-full"
                                           wire:model="lines.{{ $index }}.description"
                                           placeholder="{{ __('Article, prestation...') }}"
                                           @disabled($isLocked)>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input class="input input-sm w-full" type="number" step="0.001" min="0"
                                           wire:model.live="lines.{{ $index }}.quantity"
                                           @disabled($isLocked)>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input class="input input-sm w-full" type="number" step="0.01" min="0"
                                           wire:model.live="lines.{{ $index }}.unit_price"
                                           @disabled($isLocked)>
                                </td>
                                <td class="px-3 py-1.5">
                                    <input class="input input-sm w-full text-right bg-slate-50 text-slate-500"
                                           type="text"
                                           wire:model="lines.{{ $index }}.amount"
                                           readonly>
                                </td>
                                @if(!$isLocked)
                                <td class="px-2 py-1.5 text-center">
                                    @if(count($lines) > 1)
                                        <button type="button" class="table-action table-action-delete"
                                                wire:click="removeLine({{ $index }})"
                                                title="{{ __('Supprimer') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                 stroke="currentColor" width="15" height="15">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @endif
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50">
                            <td colspan="3" class="px-3 py-2 text-right text-[12px] font-semibold text-slate-600">
                                {{ __('Total HT') }}
                            </td>
                            <td class="px-3 py-2 text-right text-[14px] font-bold text-slate-900">
                                {{ number_format(collect($lines)->sum(fn ($l) => (float) ($l['amount'] ?? 0)), 0, ',', ' ') }}
                            </td>
                            @if(!$isLocked)<td></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- ── Documents panel (edit mode only) ───────────────────────────── --}}
        @if($purchaseOrderId)
        <div class="mt-5">
            <livewire:inovcom-dms.entity-documents
                attachable-type="purchase_order"
                :attachable-id="$purchaseOrderId"
                :key="'po-docs-'.$purchaseOrderId"
            />
        </div>
        @endif

        {{-- ── Footer actions ───────────────────────────────────────────────── --}}
        <div class="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
            <a class="btn btn-secondary" href="{{ route('tenant.achats.index', ['tenant' => $tenantCode]) }}">
                {{ __('Retour') }}
            </a>
            @if(!$isLocked)
                <button class="btn btn-primary" wire:click="save"
                        wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">
                        {{ $purchaseOrderId ? __('Mettre à jour') : __('Enregistrer') }}
                    </span>
                    <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
                </button>
            @endif
        </div>
    </section>
</div>
