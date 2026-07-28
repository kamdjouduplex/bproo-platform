@php
    $tenantCode  = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $isPaid      = $status === 'paid';
    $isCancelled = $status === 'cancelled';
    $isLocked    = $isPaid || $isCancelled;

    $statusClass = match($status) {
        'draft'     => 'bg-slate-100 text-slate-600',
        'sent'      => 'bg-blue-100 text-blue-700',
        'paid'      => 'bg-emerald-100 text-emerald-700',
        'overdue'   => 'bg-amber-100 text-amber-700',
        'cancelled' => 'bg-red-100 text-red-700',
        default     => 'bg-slate-100 text-slate-500',
    };
    $statusLabel = match($status) {
        'draft'     => __('Brouillon'),
        'sent'      => __('Envoyée'),
        'paid'      => __('Payée'),
        'overdue'   => __('En retard'),
        'cancelled' => __('Annulée'),
        default     => $status,
    };
@endphp
<div>

    {{-- ── Workflow action bar ─────────────────────────────────────────── --}}
    @if ($invoiceId)
    <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wide {{ $statusClass }}">{{ $statusLabel }}</span>
            <span class="text-[13px] text-slate-500 font-medium">{{ $code }}</span>
            @if(!$isCancelled)
            <span class="text-[13px] text-slate-500">
                {{ __('Encaissé') }}: <strong class="text-slate-700">{{ number_format($computed_amount_paid, 0, ',', ' ') }} {{ $currency }}</strong>
                &nbsp;/&nbsp;
                {{ __('Reste') }}: <strong class="{{ $computed_amount_due > 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ number_format($computed_amount_due, 0, ',', ' ') }} {{ $currency }}</strong>
            </span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="downloadPdf"
                    wire:loading.attr="disabled" wire:target="downloadPdf">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h4a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                {{ __('PDF') }}
            </button>
            @if(!$isLocked && $computed_amount_due > 0)
                <button type="button" class="btn btn-secondary btn-sm" wire:click="togglePaymentForm">
                    {{ __('Enregistrer un paiement') }}
                </button>
            @endif
            @if(in_array('sent', $allowedTransitions))
                <button type="button" class="btn btn-primary btn-sm" wire:click="sendToClient"
                        wire:loading.attr="disabled" wire:target="sendToClient"
                        wire:confirm="{{ __('Confirmer : marquer cette facture comme envoyée ?') }}">
                    <span wire:loading.remove wire:target="sendToClient">{{ __('Marquer envoyée') }}</span>
                    <span wire:loading wire:target="sendToClient">...</span>
                </button>
            @endif
            @if(in_array('cancelled', $allowedTransitions))
                <button type="button" class="btn btn-danger btn-sm" wire:click="cancelInvoice"
                        wire:loading.attr="disabled" wire:target="cancelInvoice"
                        wire:confirm="{{ __('Annuler définitivement cette facture ?') }}">
                    {{ __('Annuler') }}
                </button>
            @endif
        </div>
    </div>

    {{-- ── Payment form ─────────────────────────────────────────────────── --}}
    @if($showPaymentForm)
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-5 py-4 mb-4">
        <h3 class="text-[14px] font-semibold text-slate-800 mb-3">{{ __('Enregistrer un paiement') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="field">
                <label class="field-label">{{ __('Montant') }} <span class="text-red-500">*</span></label>
                <input class="input" type="number" step="0.01" min="0.01"
                       wire:model="pay_amount"
                       placeholder="{{ number_format($computed_amount_due, 0, ',', ' ') }}">
                @error('pay_amount') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Date') }} <span class="text-red-500">*</span></label>
                <input class="input" type="date" wire:model="pay_date">
                @error('pay_date') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Moyen de paiement') }}</label>
                <select class="input" wire:model="pay_method">
                    @foreach(\InovCom\Facturation\Support\PaymentMethodLabels::options() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label">{{ __('Référence') }}</label>
                <input class="input" wire:model="pay_reference" placeholder="{{ __('N° chèque, virement...') }}">
            </div>
        </div>
        <div class="flex gap-2 mt-3">
            <button type="button" class="btn btn-primary btn-sm" wire:click="recordPayment"
                    wire:loading.attr="disabled" wire:target="recordPayment">
                <span wire:loading.remove wire:target="recordPayment">{{ __('Valider') }}</span>
                <span wire:loading wire:target="recordPayment">...</span>
            </button>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="togglePaymentForm">{{ __('Annuler') }}</button>
        </div>
    </div>
    @endif

    {{-- ── Payment history ─────────────────────────────────────────────── --}}
    @if(count($existingPayments) > 0)
    <div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <h4 class="text-[13px] font-semibold text-slate-700">{{ __('Historique des encaissements') }}</h4>
            <span class="text-[11px] text-slate-400">{{ __('Reçu de paiement = accusé de réception archivable') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px] min-w-[520px]">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide py-1.5 pr-3">{{ __('N° reçu') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide py-1.5 pr-3">{{ __('Date') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide py-1.5 pr-3">{{ __('Moyen') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide py-1.5 pr-3">{{ __('Référence') }}</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide py-1.5 pr-3">{{ __('Montant') }}</th>
                        <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide py-1.5">{{ __('Reçu') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($existingPayments as $p)
                    <tr class="border-b border-slate-100 last:border-0" wire:key="payment-row-{{ $p['id'] }}">
                        <td class="py-2 pr-3 font-mono text-[11px] text-emerald-700 font-semibold">{{ $p['receipt_code'] ?? '—' }}</td>
                        <td class="py-2 pr-3 text-slate-600">{{ \Carbon\Carbon::parse($p['payment_date'])->format('d/m/Y') }}</td>
                        <td class="py-2 pr-3 text-slate-500">{{ $p['method_label'] ?? ucfirst($p['payment_method'] ?? '—') }}</td>
                        <td class="py-2 pr-3 text-slate-500">{{ $p['reference'] ?? '—' }}</td>
                        <td class="py-2 pr-3 text-right font-semibold text-slate-700 tabular-nums">{{ number_format($p['amount'], 0, ',', ' ') }} {{ $currency }}</td>
                        <td class="py-2 text-right">
                            <button type="button"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium rounded-md bg-white border border-slate-200 text-slate-600 hover:border-emerald-300 hover:text-emerald-700 hover:bg-emerald-50 transition-colors"
                                    wire:click="printPaymentReceipt({{ $p['id'] }})"
                                    title="{{ __('Imprimer le reçu de paiement') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                {{ __('Imprimer') }}
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endif

    {{-- ── Main form card ───────────────────────────────────────────────── --}}
    <div class="card">
        <h2 class="text-base font-semibold text-slate-800 mb-5">{{ $invoiceId ? __('Modifier la facture') : __('Nouvelle facture') }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            {{-- Code --}}
            @if($invoiceId)
                <div class="field">
                    <label class="field-label">{{ __('Code') }}</label>
                    <input class="input bg-slate-50 text-slate-500" wire:model="code" readonly disabled>
                    @error('code') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            @else
                <div class="field">
                    <label class="field-label">{{ __('Code') }}</label>
                    <input class="input bg-slate-50 text-slate-400" value="—" readonly disabled>
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Généré automatiquement (ex. FAC00001)') }}</span>
                </div>
            @endif

            {{-- Type --}}
            <div class="field">
                <label class="field-label">{{ __('Type') }}</label>
                <select class="input" wire:model.live="invoice_type" @if($isLocked) disabled @endif>
                    <option value="invoice">{{ __('Facture') }}</option>
                    <option value="proforma">{{ __('Proforma') }}</option>
                    <option value="credit_note">{{ __('Avoir') }}</option>
                </select>
                <span class="text-[11px] text-slate-400 mt-0.5 block">
                    {{ __('Les acomptes depuis un devis sont créés en proforma. Passez en « Facture » pour obtenir le numéro définitif (préfixe configurable).') }}
                </span>
                @error('invoice_type') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Client picker --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Client') }} <span class="text-red-500">*</span></label>
                @if($isLocked)
                    <input class="input bg-slate-50 text-slate-600" value="{{ $clientLabel ?? '—' }}" readonly disabled>
                @else
                    <div x-show="$wire.client_id" class="flex items-center gap-2.5 min-h-10 px-3.5 bg-sky-50 border-[1.5px] border-sky-200 rounded-lg" style="{{ $client_id ? '' : 'display:none;' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 text-sky-600 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="flex-1 text-[13px] font-semibold text-sky-700 truncate" x-text="$wire.clientLabel ?? '{{ $clientLabel }}'">{{ $clientLabel }}</span>
                        <button type="button" class="text-[11px] text-slate-500 bg-white border border-slate-200 rounded px-2 py-0.5 hover:bg-slate-50" wire:click="openClientPicker">{{ __('Changer') }}</button>
                        <button type="button" class="text-slate-400 hover:text-red-500 text-lg leading-none" wire:click="clearClient" title="{{ __('Retirer') }}">×</button>
                    </div>
                    <button type="button" x-show="!$wire.client_id"
                        class="flex items-center gap-2 w-full min-h-10 px-3.5 bg-white border-[1.5px] border-dashed border-slate-300 rounded-lg text-[13px] text-slate-500 hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50/30 transition-colors text-left"
                        style="{{ $client_id ? 'display:none;' : '' }}" wire:click="openClientPicker">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        {{ __('Rechercher et sélectionner un client...') }}
                    </button>
                @endif
                @error('client_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Project picker --}}
            <div class="field">
                <label class="field-label">{{ __('Projet') }} <span class="text-slate-400 font-normal text-[11px]">({{ __('optionnel') }})</span></label>
                @if($isLocked)
                    <input class="input bg-slate-50 text-slate-600" value="{{ $projectLabel ?? '—' }}" readonly disabled>
                @else
                    <div x-show="$wire.project_id" class="flex items-center gap-2.5 min-h-10 px-3.5 bg-emerald-50 border-[1.5px] border-emerald-200 rounded-lg" style="{{ $project_id ? '' : 'display:none;' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 text-emerald-600 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span class="flex-1 text-[13px] font-semibold text-emerald-800 truncate" x-text="$wire.projectLabel ?? '{{ $projectLabel }}'">{{ $projectLabel }}</span>
                        <button type="button" class="text-[11px] text-slate-500 bg-white border border-slate-200 rounded px-2 py-0.5 hover:bg-slate-50" wire:click="openProjectPicker">{{ __('Changer') }}</button>
                        <button type="button" class="text-slate-400 hover:text-red-500 text-lg leading-none" wire:click="clearProject" title="{{ __('Retirer') }}">×</button>
                    </div>
                    <button type="button" x-show="!$wire.project_id"
                        class="flex items-center gap-2 w-full min-h-10 px-3.5 bg-white border-[1.5px] border-dashed border-emerald-300 rounded-lg text-[13px] text-emerald-700 hover:bg-emerald-50 transition-colors text-left"
                        style="{{ $project_id ? 'display:none;' : '' }}" wire:click="openProjectPicker">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        {{ __('Rechercher un projet...') }}
                    </button>
                    @if($client_id)
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Filtré sur le client sélectionné.') }}</span>
                    @endif
                @endif
            </div>

            {{-- Quote picker --}}
            <div class="field">
                <label class="field-label">{{ __('Devis') }} <span class="text-slate-400 font-normal text-[11px]">({{ __('optionnel') }})</span></label>
                @if($isLocked)
                    <input class="input bg-slate-50 text-slate-600" value="{{ $quoteLabel ?? '—' }}" readonly disabled>
                @else
                    <div x-show="$wire.quote_id" class="flex items-center gap-2.5 min-h-10 px-3.5 bg-violet-50 border-[1.5px] border-violet-200 rounded-lg" style="{{ $quote_id ? '' : 'display:none;' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 text-violet-600 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="flex-1 text-[13px] font-semibold text-violet-800 truncate" x-text="$wire.quoteLabel ?? '{{ $quoteLabel }}'">{{ $quoteLabel }}</span>
                        <button type="button" class="text-[11px] text-slate-500 bg-white border border-slate-200 rounded px-2 py-0.5 hover:bg-slate-50" wire:click="openQuotePicker">{{ __('Changer') }}</button>
                        <button type="button" class="text-slate-400 hover:text-red-500 text-lg leading-none" wire:click="clearQuote" title="{{ __('Retirer') }}">×</button>
                    </div>
                    <button type="button" x-show="!$wire.quote_id"
                        class="flex items-center gap-2 w-full min-h-10 px-3.5 bg-white border-[1.5px] border-dashed border-violet-300 rounded-lg text-[13px] text-violet-700 hover:bg-violet-50 transition-colors text-left"
                        style="{{ $quote_id ? 'display:none;' : '' }}" wire:click="openQuotePicker">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        {{ __('Rechercher un devis accepté...') }}
                    </button>
                    @if($client_id)
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Devis acceptés du client uniquement.') }}</span>
                    @endif
                @endif
            </div>

            {{-- Title --}}
            <div class="field">
                <label class="field-label">{{ __('Titre / Objet') }}</label>
                <input class="input" wire:model="title" placeholder="{{ __('Objet de la facture') }}" @if($isLocked) disabled @endif>
            </div>

            {{-- Status --}}
            <div class="field">
                <label class="field-label">{{ __('Statut') }}</label>
                <select class="input" wire:model="status" disabled>
                    <option value="draft">{{ __('Brouillon') }}</option>
                    <option value="sent">{{ __('Envoyée') }}</option>
                    <option value="paid">{{ __('Payée') }}</option>
                    <option value="overdue">{{ __('En retard') }}</option>
                    <option value="cancelled">{{ __('Annulée') }}</option>
                </select>
                @if($invoiceId)
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Utilisez les boutons d\'action pour changer le statut.') }}</span>
                @endif
            </div>

            {{-- Currency --}}
            <div class="field">
                <label class="field-label">{{ __('Devise') }}</label>
                <select class="input" wire:model="currency" @if($isLocked) disabled @endif>
                    <option value="XOF">XOF – Franc CFA (UEMOA)</option>
                    <option value="XAF">XAF – Franc CFA (CEMAC)</option>
                    <option value="EUR">EUR – Euro</option>
                    <option value="USD">USD – Dollar US</option>
                    <option value="GBP">GBP – Livre sterling</option>
                    <option value="MAD">MAD – Dirham marocain</option>
                    <option value="GNF">GNF – Franc Guinéen</option>
                </select>
            </div>

            {{-- Issue date --}}
            <div class="field">
                <label class="field-label">{{ __("Date d'émission") }}</label>
                <input class="input" wire:model.live="issue_date" type="date" @if($isLocked) disabled @endif>
                @error('issue_date') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Payment terms --}}
            <div class="field">
                <label class="field-label">{{ __('Délai de paiement (jours)') }}</label>
                <input class="input" type="number" min="0" step="1"
                       wire:model.live="payment_terms" placeholder="30"
                       @if($isLocked) disabled @endif>
                @error('payment_terms') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Due date --}}
            <div class="field">
                <label class="field-label">{{ __("Date d'échéance") }}</label>
                <input class="input" wire:model="due_date" type="date" @if($isLocked) disabled @endif>
                @error('due_date') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Spacer --}}
            <div class="hidden sm:block"></div>

            {{-- Notes --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Notes client') }}</label>
                <textarea class="input" wire:model="notes" rows="2"
                          placeholder="{{ __('Conditions de paiement, mentions légales...') }}"
                          @if($isLocked) disabled @endif></textarea>
            </div>

            {{-- Internal notes --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Notes internes') }}</label>
                <textarea class="input" wire:model="internal_notes" rows="2"
                          placeholder="{{ __('Commentaires internes (non visibles sur le PDF)...') }}"
                          @if($isLocked) disabled @endif></textarea>
            </div>
        </div>

        {{-- ── Line items ─────────────────────────────────────────────────── --}}
        <div class="mt-6">
            <div class="flex items-center justify-between mb-2.5">
                <span class="text-sm font-semibold text-slate-800">{{ __('Lignes de facture') }}</span>
                @if(!$isLocked)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addLine">
                        + {{ __('Ajouter une ligne') }}
                    </button>
                @endif
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-lg">
                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="bg-slate-50 border-b-2 border-slate-200">
                            <th class="text-left text-slate-500 font-semibold text-[12px] px-2 py-2">{{ __('Désignation') }}</th>
                            <th class="text-right text-slate-500 font-semibold text-[12px] px-2 py-2 w-20">{{ __('Qté') }}</th>
                            <th class="text-right text-slate-500 font-semibold text-[12px] px-2 py-2 w-28">{{ __('P.U. HT') }}</th>
                            <th class="text-right text-slate-500 font-semibold text-[12px] px-2 py-2 w-28">{{ __('Montant HT') }}</th>
                            @if(!$isLocked)<th class="w-9"></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $index => $line)
                        <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors" wire:key="inv-line-{{ $index }}">
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full" wire:model="lines.{{ $index }}.description"
                                       placeholder="{{ __('Prestation, fourniture...') }}"
                                       @if($isLocked) disabled @endif>
                            </td>
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full text-right" type="number" step="0.001" min="0"
                                       wire:model.live="lines.{{ $index }}.quantity"
                                       @if($isLocked) disabled @endif>
                            </td>
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full text-right" type="number" step="0.01" min="0"
                                       wire:model.live="lines.{{ $index }}.unit_price"
                                       @if($isLocked) disabled @endif>
                            </td>
                            <td class="px-1 py-1">
                                <input class="input input-sm w-full text-right bg-slate-50 text-slate-600 cursor-default" type="text"
                                       wire:model="lines.{{ $index }}.amount" readonly>
                            </td>
                            @if(!$isLocked)
                            <td class="px-1 py-1 text-center">
                                @if (count($lines) > 1)
                                    <button type="button" class="table-action table-action-delete"
                                            wire:click="removeLine({{ $index }})"
                                            title="{{ __('Supprimer') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Financials ──────────────────────────────────────────────────── --}}
        <div class="flex flex-wrap items-start justify-between gap-6 mt-5">
            <div class="flex flex-wrap gap-4 items-end flex-1 min-w-[200px]">
                <div class="field flex-1 min-w-[140px]">
                    <label class="field-label">{{ __('Remise globale (%)') }}</label>
                    <input class="input" type="number" step="0.01" min="0" max="100"
                           wire:model.live="discount_percent" placeholder="0"
                           @if($isLocked) disabled @endif>
                    @error('discount_percent') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field flex-1 min-w-[140px]">
                    <label class="field-label">{{ __('TVA (%)') }}</label>
                    <input class="input" type="number" step="0.01" min="0" max="100"
                           wire:model.live="tax_rate" placeholder="0"
                           @if($isLocked) disabled @endif>
                    @error('tax_rate') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="min-w-[280px] bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                <table class="w-full border-collapse">
                    <tbody>
                        <tr>
                            <td class="py-1 text-[14px] text-slate-500">{{ __('Total HT') }}</td>
                            <td class="py-1 text-right text-[14px] font-medium text-slate-800 tabular-nums">{{ number_format($computed_total_ht, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        @if($computed_discount > 0)
                        <tr>
                            <td class="py-1 text-[14px] text-slate-500">{{ __('Remise') }} ({{ $discount_percent }}%)</td>
                            <td class="py-1 text-right text-[14px] font-medium text-red-600 tabular-nums">– {{ number_format($computed_discount, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 text-[14px] text-slate-500">{{ __('Net HT') }}</td>
                            <td class="py-1 text-right text-[14px] font-medium text-slate-800 tabular-nums">{{ number_format($computed_net_ht, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        @endif
                        @if($computed_tax > 0)
                        <tr>
                            <td class="py-1 text-[14px] text-slate-500">{{ __('TVA') }} ({{ $tax_rate }}%)</td>
                            <td class="py-1 text-right text-[14px] font-medium text-slate-800 tabular-nums">+ {{ number_format($computed_tax, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        @endif
                        <tr class="border-t-2 border-slate-200">
                            <td class="pt-2 pb-1 text-[14px] font-semibold text-slate-700">{{ __('Total TTC') }}</td>
                            <td class="pt-2 pb-1 text-right text-[16px] font-bold text-slate-900 tabular-nums">{{ number_format($computed_total_ttc, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        @if($computed_amount_paid > 0)
                        <tr class="border-t border-dashed border-slate-200">
                            <td class="pt-1.5 text-[14px] text-emerald-700">{{ __('Encaissé') }}</td>
                            <td class="pt-1.5 text-right text-[14px] font-medium text-emerald-700 tabular-nums">– {{ number_format($computed_amount_paid, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 text-[14px] font-bold text-slate-700">{{ __('Solde dû') }}</td>
                            <td class="py-1 text-right text-[14px] font-bold tabular-nums {{ $computed_amount_due > 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ number_format($computed_amount_due, 0, ',', ' ') }} {{ $currency }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Bottom actions ──────────────────────────────────────────────── --}}
        <div class="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
            <a class="btn btn-secondary" href="{{ route('tenant.facturation.index', ['tenant' => $tenantCode]) }}">
                {{ $isLocked ? __('Retour à la liste') : __('Retour') }}
            </a>
            @if(!$isLocked)
            <button class="btn btn-primary" wire:click="save"
                    wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $invoiceId ? __('Mettre à jour') : __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
            </button>
            @endif
        </div>
    </div>

    {{-- ══ CLIENT PICKER ════════════════════════════════════════════════ --}}
    @if(!$isLocked)
    <div x-show="$wire.showClientPicker" wire:click.self="closeClientPicker"
         class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/55 backdrop-blur-sm"
         style="display:none;">
        <div class="bg-white rounded-2xl w-[520px] max-w-[calc(100vw-32px)] max-h-[540px] flex flex-col shadow-2xl overflow-hidden"
             x-data x-effect="if ($wire.showClientPicker) $nextTick(() => $refs.invClientInput?.focus())">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
                <span class="text-[14px] font-bold text-slate-900">{{ __('Sélectionner un client') }}</span>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-100" wire:click="closeClientPicker">×</button>
            </div>
            <div class="px-4 py-3 border-b border-slate-100">
                <input x-ref="invClientInput" wire:model.live.debounce.300ms="clientSearch"
                       class="input" placeholder="{{ __('Rechercher par nom, code ou email...') }}" autocomplete="off">
            </div>
            <div class="overflow-y-auto flex-1">
                @forelse ($clientResults as $c)
                    <div class="flex items-center gap-3 px-4 py-2.5 cursor-pointer border-b border-slate-50 hover:bg-slate-50" wire:click="selectClient({{ $c['id'] }})" wire:key="inv-client-{{ $c['id'] }}">
                        <div class="flex-1 text-[13px] font-semibold text-slate-800">{{ $c['name'] }}</div>
                        <span class="font-mono text-[11px] text-slate-400">{{ $c['code'] }}</span>
                    </div>
                @empty
                    <div class="py-9 text-center text-slate-400 text-[13px]">
                        @if(trim($clientSearch ?? '') !== '')
                            {{ __('Aucun client pour « :q »', ['q' => $clientSearch]) }}
                        @else
                            {{ __('Saisissez au moins 2 caractères') }}
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ PROJECT PICKER ═══════════════════════════════════════════════ --}}
    <div x-show="$wire.showProjectPicker" wire:click.self="closeProjectPicker"
         class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/55 backdrop-blur-sm"
         style="display:none;">
        <div class="bg-white rounded-2xl w-[560px] max-w-[calc(100vw-32px)] max-h-[540px] flex flex-col shadow-2xl overflow-hidden"
             x-data x-effect="if ($wire.showProjectPicker) $nextTick(() => $refs.invProjectInput?.focus())">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
                <div>
                    <span class="text-[14px] font-bold text-slate-900">{{ __('Sélectionner un projet') }}</span>
                    @if($client_id)
                    <p class="text-[11px] text-slate-500 mt-0.5">{{ __('Projets du client sélectionné') }}</p>
                    @endif
                </div>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-100" wire:click="closeProjectPicker">×</button>
            </div>
            <div class="px-4 py-3 border-b border-slate-100">
                <input x-ref="invProjectInput" wire:model.live.debounce.300ms="projectSearch"
                       class="input" placeholder="{{ __('Rechercher par code ou intitulé...') }}" autocomplete="off">
            </div>
            <div class="overflow-y-auto flex-1">
                @forelse ($projectResults as $p)
                    <div class="flex items-center gap-3 px-4 py-2.5 cursor-pointer border-b border-slate-50 hover:bg-slate-50" wire:click="selectProject({{ $p['id'] }})" wire:key="inv-project-{{ $p['id'] }}">
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-semibold text-slate-800 truncate">{{ $p['title'] }}</div>
                            @if(!empty($p['client_name']))
                            <div class="text-[11px] text-slate-400 truncate">{{ $p['client_name'] }}</div>
                            @endif
                        </div>
                        <span class="font-mono text-[11px] text-emerald-700 flex-shrink-0">{{ $p['code'] }}</span>
                    </div>
                @empty
                    <div class="py-9 text-center text-slate-400 text-[13px]">
                        @if(trim($projectSearch ?? '') !== '')
                            {{ __('Aucun projet pour « :q »', ['q' => $projectSearch]) }}
                        @else
                            {{ __('Saisissez au moins 2 caractères') }}
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ QUOTE PICKER ═══════════════════════════════════════════════════ --}}
    <div x-show="$wire.showQuotePicker" wire:click.self="closeQuotePicker"
         class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/55 backdrop-blur-sm"
         style="display:none;">
        <div class="bg-white rounded-2xl w-[560px] max-w-[calc(100vw-32px)] max-h-[540px] flex flex-col shadow-2xl overflow-hidden"
             x-data x-effect="if ($wire.showQuotePicker) $nextTick(() => $refs.invQuoteInput?.focus())">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
                <div>
                    <span class="text-[14px] font-bold text-slate-900">{{ __('Sélectionner un devis accepté') }}</span>
                    @if($client_id)
                    <p class="text-[11px] text-slate-500 mt-0.5">{{ __('Devis du client sélectionné') }}</p>
                    @endif
                </div>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-100" wire:click="closeQuotePicker">×</button>
            </div>
            <div class="px-4 py-3 border-b border-slate-100">
                <input x-ref="invQuoteInput" wire:model.live.debounce.300ms="quoteSearch"
                       class="input" placeholder="{{ __('Rechercher par code ou objet...') }}" autocomplete="off">
            </div>
            <div class="overflow-y-auto flex-1">
                @forelse ($quoteResults as $q)
                    <div class="flex items-center gap-3 px-4 py-2.5 cursor-pointer border-b border-slate-50 hover:bg-slate-50" wire:click="selectQuote({{ $q['id'] }})" wire:key="inv-quote-{{ $q['id'] }}">
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-semibold text-slate-800 truncate">{{ $q['title'] }}</div>
                            @if(!empty($q['client_name']))
                            <div class="text-[11px] text-slate-400 truncate">{{ $q['client_name'] }}</div>
                            @endif
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="font-mono text-[11px] text-violet-700 block">{{ $q['code'] }}</span>
                            <span class="text-[10px] text-slate-400 tabular-nums">{{ number_format($q['total_ttc'] ?? 0, 0, ',', ' ') }} {{ $q['currency'] ?? '' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-9 text-center text-slate-400 text-[13px]">
                        @if(trim($quoteSearch ?? '') !== '')
                            {{ __('Aucun devis pour « :q »', ['q' => $quoteSearch]) }}
                        @else
                            {{ __('Saisissez au moins 2 caractères') }}
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>
