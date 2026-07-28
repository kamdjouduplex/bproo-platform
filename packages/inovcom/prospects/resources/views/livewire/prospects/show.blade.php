@php
    use InovCom\Prospects\Models\Prospect;
    $pipeline = ['nouveau', 'contacte', 'qualifie', 'converti'];
    $statusOrder = array_flip($pipeline);
    $currentIdx = $statusOrder[$prospect->status] ?? null;
@endphp

<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div class="page-actions" style="margin-bottom:16px;">
        <a class="btn btn-secondary" href="{{ route('tenant.prospects.index', ['tenant' => $tenantCode]) }}">← Prospects</a>
        @if ($canUpdate)
            <a class="btn btn-secondary" href="{{ route('tenant.prospects.edit', [$prospect->id, 'tenant' => $tenantCode]) }}">Modifier la fiche</a>
        @endif
        @if ($prospect->convertedClient)
            <a class="btn btn-primary" href="{{ route('tenant.clients.show', [$prospect->converted_client_id, 'tenant' => $tenantCode]) }}">
                Client {{ $prospect->convertedClient->code }}
            </a>
        @endif
    </div>

    <div class="prospect-show-layout">
        <div style="display:flex;flex-direction:column;gap:16px;">
            <section class="card" style="padding:20px;">
                <div class="prospect-hero">
                    <div>
                        <div class="prospect-hero__ref">{{ $prospect->reference }}</div>
                        <h2 class="prospect-hero__title">{{ $prospect->name }}</h2>
                        <div class="prospect-hero__sub">
                            {{ Prospect::typeOptions()[$prospect->type] ?? $prospect->type }}
                            · {{ Prospect::sourceLabel($prospect->source) }}
                            · {{ Prospect::statusHints()[$prospect->status] ?? '' }}
                        </div>
                    </div>
                    <span class="badge {{ Prospect::statusBadgeClass($prospect->status) }}" style="align-self:flex-start;font-size:12px;padding:4px 10px;">
                        {{ Prospect::statusLabel($prospect->status) }}
                    </span>
                </div>

                @if ($prospect->status !== 'perdu')
                    <div class="prospect-funnel" aria-label="Étapes du pipeline">
                        @foreach ($pipeline as $step)
                            @php
                                $stepIdx = $statusOrder[$step];
                                $cls = '';
                                if ($prospect->isConverted() || ($currentIdx !== null && $stepIdx < $currentIdx)) {
                                    $cls = 'is-done';
                                } elseif ($currentIdx !== null && $stepIdx === $currentIdx) {
                                    $cls = 'is-current';
                                }
                            @endphp
                            <div class="prospect-funnel__step {{ $cls }}">{{ Prospect::statusLabel($step) }}</div>
                        @endforeach
                    </div>
                @else
                    <div class="prospect-funnel">
                        <div class="prospect-funnel__step is-lost">Perdu — {{ $prospect->lost_reason ?: 'motif non renseigné' }}</div>
                    </div>
                @endif

                <dl class="prospect-dl">
                    <div>
                        <dt>Téléphone</dt>
                        <dd>
                            @if ($prospect->phone)
                                <a href="tel:{{ $prospect->phone }}">{{ $prospect->phone }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>E-mail</dt>
                        <dd>
                            @if ($prospect->email)
                                <a href="mailto:{{ $prospect->email }}">{{ $prospect->email }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="prospect-dl__full">
                        <dt>Adresse</dt>
                        <dd>{{ $prospect->address ?: '—' }}</dd>
                    </div>
                    @if ($prospect->type === 'company')
                        <div>
                            <dt>RCCM</dt>
                            <dd>{{ $prospect->rccm ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt>NIU</dt>
                            <dd>{{ $prospect->niu ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt>N° fiscal</dt>
                            <dd>{{ $prospect->tax_id ?: '—' }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt>Coût du lead</dt>
                        <dd class="prospect-money">{{ fmt_money((float) $prospect->cost) }} FCFA</dd>
                    </div>
                    <div>
                        <dt>CA potentiel</dt>
                        <dd class="prospect-money">
                            {{ $prospect->expected_value !== null ? fmt_money((float) $prospect->expected_value) . ' FCFA' : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt>Commercial</dt>
                        <dd>{{ $prospect->owner?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Créé le</dt>
                        <dd>{{ optional($prospect->created_at)->format('d/m/Y H:i') }}</dd>
                    </div>
                    @if ($prospect->notes)
                        <div class="prospect-dl__full">
                            <dt>Notes</dt>
                            <dd style="white-space:pre-wrap;">{{ $prospect->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="card" style="padding:20px;">
                <h3 class="form-section-title" style="margin-top:0;">Historique</h3>
                @forelse ($prospect->activities as $activity)
                    <div class="prospect-activity">
                        <div class="prospect-activity__head">
                            <strong>{{ \InovCom\Prospects\Models\ProspectActivity::typeLabel($activity->type) }}</strong>
                            <span style="color:#94a3b8;">{{ optional($activity->created_at)->format('d/m/Y H:i') }} · {{ $activity->user?->name ?? '—' }}</span>
                        </div>
                        <div class="prospect-activity__body">{{ $activity->body }}</div>
                    </div>
                @empty
                    <p class="field-hint" style="margin:0;">Aucune activité pour l’instant — notez un appel ou une visite.</p>
                @endforelse
            </section>
        </div>

        <div style="display:flex;flex-direction:column;gap:16px;">
            @if ($canUpdate)
                <section class="card" style="padding:16px;">
                    <h3 class="form-section-title" style="margin-top:0;">Pipeline</h3>
                    <p class="prospect-form-hint" style="margin:0 0 10px;">
                        <strong>Qualifié</strong> = le besoin, le budget et l’intention d’achat sont confirmés.
                    </p>
                    <div class="field">
                        <select class="input" wire:model.live="newStatus">
                            @foreach (Prospect::statusOptions() as $value => $label)
                                @if ($value !== 'converti')
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                        @if ($newStatus && isset(Prospect::statusHints()[$newStatus]))
                            <span class="prospect-form-hint">{{ Prospect::statusHints()[$newStatus] }}</span>
                        @endif
                    </div>
                    @if ($newStatus === 'perdu')
                        <div class="field">
                            <label class="field-label">Motif de perte *</label>
                            <input class="input" wire:model="lostReason" placeholder="Ex. Budget insuffisant, concurrent choisi…">
                        </div>
                    @endif
                    <button type="button" class="btn btn-secondary" wire:click="changeStatus">Mettre à jour le statut</button>
                </section>

                <section class="card" style="padding:16px;">
                    <h3 class="form-section-title" style="margin-top:0;">Activité</h3>
                    <div class="field">
                        <select class="input" wire:model="activityType">
                            @foreach ($activityTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <textarea class="input" rows="3" wire:model="activityBody" placeholder="Compte-rendu du contact…"></textarea>
                        @error('activityBody') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <button type="button" class="btn btn-primary" wire:click="addActivity">Enregistrer l’activité</button>
                </section>
            @endif

            @if ($canConvert)
                <section class="prospect-convert">
                    <h3 class="form-section-title" style="margin-top:0;">Convertir en client</h3>
                    <p class="prospect-form-hint" style="margin:0 0 10px;">
                        Crée la fiche client avec les données du prospect.
                    </p>

                    @if (! $readyToConvert)
                        <div class="prospect-convert__gaps">
                            <strong>Complétez la fiche avant de convertir :</strong>
                            <ul>
                                @foreach ($conversionGaps as $gap)
                                    <li>{{ $gap }}</li>
                                @endforeach
                            </ul>
                            @if ($canUpdate)
                                <div style="margin-top:10px;">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.prospects.edit', [$prospect->id, 'tenant' => $tenantCode]) }}">Compléter la fiche</a>
                                </div>
                            @endif
                        </div>
                    @endif

                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:12px;">
                        <input type="checkbox" wire:model="createQuotationAfterConvert" @disabled(! $readyToConvert)>
                        Proposer un devis ensuite
                    </label>
                    <button
                        type="button"
                        class="btn btn-primary"
                        wire:click="convert"
                        wire:confirm="Convertir ce prospect en client ?"
                        @disabled(! $readyToConvert)
                    >
                        Convertir en client
                    </button>
                </section>
            @endif

            @if ($canDelete)
                <button type="button" class="btn btn-error btn-sm" wire:click="delete" wire:confirm="Supprimer définitivement ce prospect ?">
                    Supprimer le prospect
                </button>
            @endif
        </div>
    </div>
</div>
