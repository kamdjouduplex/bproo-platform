<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">Paiement #{{ $payment->id }}</h2>
                <p class="sch-detail-toolbar__hint">
                    {{ $payment->typeLabel() }} ·
                    <span class="badge {{ $payment->status === 'verified' ? 'badge-success' : 'badge-secondary' }}">
                        {{ $payment->statusLabel() }}
                    </span>
                </p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.payments.index', ['tenant' => $tenantCode]) }}">Retour</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.payments.show', ['tenant' => $tenantCode, 'id' => $payment->id]) }}">Voir</a>
                    @if($payment->status !== 'verified')
                        <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                    @endif
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.payments.manage', ['tenant' => $tenantCode, 'id' => $payment->id]) }}">Gérer</a>
                @endif
            </div>
        </div>

        {{-- Workflow --}}
        <div style="display:flex; gap:8px; flex-wrap:wrap; padding:0 16px 16px;">
            @foreach($steps as $i => $step)
                <div style="flex:1; min-width:140px; border:1px solid {{ $step['current'] ? '#2563eb' : '#e2e8f0' }}; background:{{ $step['done'] ? '#eff6ff' : '#f8fafc' }}; border-radius:10px; padding:10px 12px;">
                    <div style="font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.04em;">Étape {{ $i + 1 }}</div>
                    <div style="font-weight:700; margin-top:2px; color:#0f172a;">{{ $step['label'] }}</div>
                    <div style="font-size:12px; margin-top:4px; color:{{ $step['done'] ? '#16a34a' : ($step['current'] ? '#2563eb' : '#94a3b8') }};">
                        {{ $step['done'] ? 'Terminé' : ($step['current'] ? 'En cours' : 'À venir') }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="sch-info-grid">
            @foreach([
                'Élève' => ($payment->student?->student_code.' — '.$payment->student?->full_name),
                'Année académique' => $payment->academicYear?->name ?? '—',
                'Méthode' => $payment->typeLabel(),
                'Statut' => $payment->statusLabel(),
                'Montant' => number_format((float) $payment->amount, 0, ',', ' ').' '.$payment->currency_code,
                'Payeur' => $payment->payer_name ?? '—',
                'Banque' => $payment->bank_name ?? '—',
                'Détail canal' => $payment->channel_detail ?? '—',
                'Référence / bordereau' => $payment->reference ?? '—',
                'Payé le' => $payment->paid_at?->format('d/m/Y H:i') ?? '—',
                'Validé le' => $payment->verified_at?->format('d/m/Y H:i') ?? '—',
                'Validé par' => $payment->verified_by_name ?? '—',
                'Reçu école' => $receipt?->receipt_number ?? '—',
                'Notes' => $payment->notes ?? '—',
            ] as $label => $value)
                <div class="sch-info-item">
                    <span class="sch-info-item__label">{{ $label }}</span>
                    <div class="sch-info-item__value">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        @if($payment->status === 'rejected' && $payment->rejected_reason)
            <div style="margin:0 16px 16px; padding:12px; border-radius:8px; border:1px solid #fecaca; background:#fef2f2; color:#991b1b;">
                <strong>Motif du rejet :</strong> {{ $payment->rejected_reason }}
            </div>
        @endif

        {{-- Justificatif --}}
        <div style="margin:0 16px 16px; padding:14px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc;">
            <div style="font-weight:700; margin-bottom:8px;">Justificatif (reçu / bordereau)</div>
            @if($payment->hasProof())
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <span>{{ $payment->proof_original_name ?? 'Fichier joint' }}</span>
                    @if($payment->proofUrl($tenantCode))
                        <a class="btn btn-secondary btn-sm" href="{{ $payment->proofUrl($tenantCode) }}" target="_blank" rel="noopener">Ouvrir le justificatif</a>
                    @endif
                </div>
            @else
                <p style="margin:0; color:#64748b; font-size:13px;">
                    @if(\School\Support\SchoolPaymentCatalog::requiresProof($payment->payment_type))
                        Aucun bordereau joint — la validation est bloquée jusqu’à dépôt du justificatif.
                    @else
                        Aucun justificatif requis pour cette méthode.
                    @endif
                </p>
            @endif

            @if($isManage && $payment->status !== 'verified')
                <div style="margin-top:12px; display:grid; gap:8px; max-width:420px;">
                    <label class="label">Ajouter / remplacer le justificatif</label>
                    <input class="input" type="file" wire:model="proofFile" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    @error('proofFile') <span class="text-error">{{ $message }}</span> @enderror
                    <button type="button" class="btn btn-secondary btn-sm" style="width:fit-content;" wire:click="uploadProof" wire:loading.attr="disabled">
                        Enregistrer le justificatif
                    </button>
                </div>
            @endif
        </div>

        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions de validation</div>
                @if($payment->status !== 'verified')
                    <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @endif

                @if(in_array($payment->status, ['pending', 'rejected'], true))
                    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; width:100%; margin-top:8px;">
                        <div style="min-width:200px;">
                            <label class="label">Validateur</label>
                            <input class="input" wire:model="verifierName" placeholder="Nom du caissier / comptable">
                            @error('verifierName') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <button
                            class="btn btn-primary btn-sm"
                            wire:click="verify"
                            wire:confirm="Confirmer la validation de ce paiement après contrôle du justificatif ?"
                            @disabled(! $payment->canVerify())
                            title="{{ $payment->canVerify() ? 'Valider' : 'Justificatif ou référence manquant' }}"
                        >
                            Valider le paiement
                        </button>
                    </div>
                    @if(! $payment->canVerify())
                        <p style="width:100%; margin:8px 0 0; font-size:12px; color:#b45309;">
                            Validation impossible tant que le bordereau / la référence requis(e) n’est pas fourni(e).
                        </p>
                    @endif

                    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; width:100%; margin-top:12px;">
                        <div style="flex:1; min-width:220px;">
                            <label class="label">Motif de rejet</label>
                            <input class="input" wire:model="rejectReason" placeholder="Ex. bordereau illisible, montant incorrect…">
                            @error('rejectReason') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <button class="btn btn-secondary btn-sm" wire:click="reject" wire:confirm="Rejeter ce paiement ?">Rejeter</button>
                    </div>
                @endif

                @if($receipt)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.receipts.print', ['tenant' => $tenantCode, 'payment' => $payment->id]) }}" onclick="return schoolOpenPrint(this.href)">Imprimer le reçu école</a>
                @endif
            </div>
        @elseif($receipt)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Documents</div>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.receipts.print', ['tenant' => $tenantCode, 'payment' => $payment->id]) }}" onclick="return schoolOpenPrint(this.href)">Imprimer le reçu école</a>
            </div>
        @endif
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head">
                    <h3 class="sch-modal__title">Modifier le paiement</h3>
                    <button class="sch-modal__close" wire:click="cancel">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div class="form-span-2">@include('school::livewire.partials.searchable-student')</div>
                        <div>
                            <label class="label">Année</label>
                            <select class="input" wire:model="academicYearId">
                                <option value="">—</option>
                                @foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Méthode</label>
                            <select class="input" wire:model.live="paymentType">
                                @foreach($methods as $key => $meta)
                                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="label">Montant</label><input class="input" type="number" step="0.01" wire:model="amount"></div>
                        <div><label class="label">Devise</label><input class="input" wire:model="currencyCode"></div>
                        <div class="form-span-2"><label class="label">Payeur</label><input class="input" wire:model="payerName"></div>
                        <div><label class="label">Banque</label><input class="input" wire:model="bankName"></div>
                        <div><label class="label">Détail canal</label><input class="input" wire:model="channelDetail" placeholder="Opérateur / n° chèque"></div>
                        <div class="form-span-2"><label class="label">Référence / bordereau</label><input class="input" wire:model="reference"></div>
                        <div class="form-span-2">
                            <label class="label">Nouveau justificatif (optionnel)</label>
                            <input class="input" type="file" wire:model="proofFile" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                        <div class="form-span-2"><label class="label">Notes</label><textarea class="input" rows="2" wire:model="notes"></textarea></div>
                    </div>
                </div>
                <div class="sch-modal__foot">
                    <button class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button class="btn btn-primary" wire:click="save">Enregistrer</button>
                </div>
            </div>
        </div>
    @endif
</div>
