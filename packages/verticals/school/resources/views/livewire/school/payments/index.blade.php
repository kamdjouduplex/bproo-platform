<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Paiements</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouveau paiement</button>
            </div>
        </div>

        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Élève, bordereau, banque…">
            <select class="input" wire:model.live="filterYearId" style="max-width:200px;">
                <option value="">Toutes années</option>
                @foreach($years as $y)
                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="filterType" style="max-width:180px;">
                <option value="">Toutes méthodes</option>
                @foreach($methods as $key => $meta)
                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="filterStatus" style="max-width:180px;">
                <option value="">Tous statuts</option>
                <option value="pending">En attente</option>
                <option value="verified">Validé</option>
                <option value="rejected">Rejeté</option>
            </select>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th>Méthode</th>
                        <th>Référence</th>
                        <th>Justificatif</th>
                        <th>Statut</th>
                        <th>Montant</th>
                        <th>Date</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $p)
                        <tr>
                            <td>
                                <strong>{{ $p->student?->student_code }}</strong>
                                — {{ $p->student?->full_name }}
                            </td>
                            <td>{{ $p->typeLabel() }}</td>
                            <td>{{ $p->reference ?? '—' }}</td>
                            <td>
                                @if($p->hasProof())
                                    <span class="badge badge-success">Oui</span>
                                @elseif(\School\Support\SchoolPaymentCatalog::requiresProof($p->payment_type))
                                    <span class="badge badge-secondary">Manquant</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($p->status === 'verified')
                                    <span class="badge badge-success">Validé</span>
                                @elseif($p->status === 'rejected')
                                    <span class="badge badge-secondary">Rejeté</span>
                                @else
                                    <span class="badge badge-secondary">En attente</span>
                                @endif
                            </td>
                            <td>{{ number_format((float) $p->amount, 0, ',', ' ') }} {{ $p->currency_code }}</td>
                            <td>{{ $p->paid_at?->format('d/m/Y H:i') ?? $p->created_at?->format('d/m/Y') }}</td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.payments.show', ['tenant' => $tenantCode, 'id' => $p->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.payments.manage', ['tenant' => $tenantCode, 'id' => $p->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Aucun paiement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $payments->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <h3 class="sch-modal__title">Nouveau paiement</h3>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Année académique</label>
                            <select class="input" wire:model="academicYearId">
                                <option value="">—</option>
                                @foreach($years as $y)
                                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                                @endforeach
                            </select>
                            @error('academicYearId') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Méthode de paiement</label>
                            <select class="input" wire:model.live="paymentType">
                                @foreach($methods as $key => $meta)
                                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                            <p style="margin:6px 0 0;font-size:12px;color:#64748b;">{{ $methods[$paymentType]['hint'] ?? '' }}</p>
                        </div>
                        <div class="form-span-2">
                            @include('school::livewire.partials.searchable-student')
                        </div>
                        <div>
                            <label class="label">Montant</label>
                            <input class="input" wire:model="amount" type="number" step="0.01" min="0">
                            @error('amount') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Devise</label>
                            <input class="input" wire:model="currencyCode" type="text" placeholder="XOF">
                        </div>
                        <div class="form-span-2">
                            <label class="label">Payeur (parent / tuteur)</label>
                            <input class="input" wire:model="payerName" type="text" placeholder="Nom du payeur">
                        </div>

                        @if($paymentType === 'bank')
                            <div>
                                <label class="label">Banque *</label>
                                <input class="input" wire:model="bankName" type="text" placeholder="Ex. Afriland, BICEC…">
                                @error('bankName') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="label">N° bordereau / reçu banque *</label>
                                <input class="input" wire:model="reference" type="text" placeholder="N° du bordereau de versement">
                                @error('reference') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                        @elseif($paymentType === 'mobile_money')
                            <div>
                                <label class="label">Opérateur *</label>
                                <select class="input" wire:model="channelDetail">
                                    <option value="">—</option>
                                    <option value="MTN">MTN MoMo</option>
                                    <option value="Orange">Orange Money</option>
                                    <option value="Moov">Moov Money</option>
                                    <option value="Autre">Autre</option>
                                </select>
                                @error('channelDetail') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="label">ID / n° transaction *</label>
                                <input class="input" wire:model="reference" type="text" placeholder="Référence transaction">
                                @error('reference') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                        @elseif($paymentType === 'cheque')
                            <div>
                                <label class="label">Banque émettrice</label>
                                <input class="input" wire:model="bankName" type="text">
                            </div>
                            <div>
                                <label class="label">N° de chèque *</label>
                                <input class="input" wire:model="reference" type="text" placeholder="Numéro du chèque">
                                @error('reference') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-span-2">
                                <label class="label">Complément</label>
                                <input class="input" wire:model="channelDetail" type="text" placeholder="Titulaire, agence…">
                            </div>
                        @elseif($paymentType === 'card')
                            <div class="form-span-2">
                                <label class="label">Code d’autorisation (optionnel)</label>
                                <input class="input" wire:model="reference" type="text">
                            </div>
                        @else
                            <div class="form-span-2">
                                <label class="label">Référence (optionnel)</label>
                                <input class="input" wire:model="reference" type="text" placeholder="Ticket caisse…">
                            </div>
                        @endif

                        @if(in_array($paymentType, ['bank', 'mobile_money', 'cheque'], true))
                            <div class="form-span-2">
                                <label class="label">
                                    @if($paymentType === 'bank') Justificatif — reçu / bordereau de versement *
                                    @elseif($paymentType === 'cheque') Scan / photo du chèque *
                                    @else Capture / reçu Mobile Money *
                                    @endif
                                </label>
                                <input class="input" type="file" wire:model="proofFile" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                <p style="margin:6px 0 0;font-size:12px;color:#64748b;">PDF ou image — max 8 Mo. Obligatoire pour valider ce paiement.</p>
                                @error('proofFile') <span class="text-error">{{ $message }}</span> @enderror
                                <div wire:loading wire:target="proofFile" style="font-size:12px;color:#2563eb;margin-top:4px;">Téléversement…</div>
                            </div>
                        @endif

                        <div class="form-span-2">
                            <label class="label">Notes internes</label>
                            <textarea class="input" rows="2" wire:model="notes" placeholder="Commentaire caissier / comptabilité…"></textarea>
                        </div>
                    </div>
                </div>
                <div class="sch-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                        {{ in_array($paymentType, ['onsite','card'], true) ? 'Enregistrer + générer reçu' : 'Enregistrer (à valider)' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
