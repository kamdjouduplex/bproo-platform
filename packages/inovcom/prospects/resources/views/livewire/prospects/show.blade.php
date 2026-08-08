@php
    use InovCom\Prospects\Models\Prospect;
    $pipeline = ['qualifie', 'negociation', 'gagne', 'converti'];
    $statusOrder = array_flip($pipeline);
    $currentIdx = $statusOrder[$prospect->status] ?? null;
@endphp

<div class="page-body crm-page">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <div class="page-actions" style="margin-bottom:16px;">
        <a class="btn btn-secondary" href="{{ route('tenant.prospects.index', ['tenant' => $tenantCode]) }}">← Prospects</a>
        @if ($crmEnabled && ! $prospect->isConverted() && ! $prospect->isLost() && Route::has('tenant.crm.opportunities'))
            <a class="btn btn-primary" href="{{ route('tenant.crm.opportunities', ['tenant' => $tenantCode]) }}">Pipeline opportunités</a>
        @endif
        @if ($canUpdate)
            <a class="btn btn-secondary" href="{{ route('tenant.prospects.edit', [$prospect->id, 'tenant' => $tenantCode]) }}">Modifier la fiche</a>
        @endif
        @if ($prospect->convertedClient)
            <a class="btn btn-primary" href="{{ route('tenant.clients.show', [$prospect->converted_client_id, 'tenant' => $tenantCode]) }}">
                Client {{ $prospect->convertedClient->code }}
            </a>
        @endif
    </div>

    @if ($prospect->status !== 'perdu')
        <div class="prospect-funnel" aria-label="Étapes du pipeline" style="margin-bottom:16px;">
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
        <div class="prospect-funnel" style="margin-bottom:16px;">
            <div class="prospect-funnel__step is-lost">Perdu — {{ $prospect->lost_reason ?: 'motif non renseigné' }}</div>
        </div>
    @endif

    <div class="prospect-show-layout">
        <div class="crm-drawer-shell">
            @include('inovcom-prospects::partials.prospect-drawer', [
                'prospect' => $prospect,
                'canManage' => $canUpdate,
                'canConvert' => $canConvert && $readyToConvert,
                'canUpdate' => $canUpdate,
                'showPanelActions' => $showPanelActions,
                'compact' => true,
            ])
        </div>

        <div class="prospect-show-aside">
            @if ($canUpdate)
                <section class="card" style="padding:16px;">
                    <h3 class="form-section-title" style="margin-top:0;">Pipeline</h3>
                    <p class="prospect-form-hint" style="margin:0 0 10px;">
                        Pipeline : <strong>Qualifié</strong> → <strong>Négociation</strong> → <strong>Gagné</strong>, puis conversion client.
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
                    <label class="field-toggle" style="margin-bottom:10px;">
                        <input type="checkbox" wire:model.live="activityIsPlanned">
                        Planifier pour plus tard (prochaine action)
                    </label>
                    @if ($activityIsPlanned)
                        <div class="field">
                            <label class="field-label">Échéance</label>
                            <input class="input" type="datetime-local" wire:model="activityDueAt">
                            @error('activityDueAt') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    <div class="field">
                        <textarea class="input" rows="3" wire:model="activityBody" placeholder="{{ $activityIsPlanned ? 'Objectif de l’action…' : 'Compte-rendu du contact…' }}"></textarea>
                        @error('activityBody') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <button type="button" class="btn btn-primary" wire:click="addActivity">
                        {{ $activityIsPlanned ? 'Planifier l’action' : 'Enregistrer l’activité' }}
                    </button>
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

    @if ($showAssignModal)
        <div class="crm-modal-backdrop" wire:click.self="closeAssignModal">
            <div class="crm-modal" role="dialog" aria-modal="true">
                <div class="crm-modal__head">
                    <div>
                        <h3 class="crm-modal__title">Assigner un commercial</h3>
                        <p class="crm-modal__sub">{{ $prospect->name }}</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeAssignModal">Fermer</button>
                </div>
                <div class="field">
                    <label class="field-label">Commercial responsable</label>
                    <select class="input" wire:model="assignOwnerId">
                        <option value="">— Non assigné —</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="crm-modal__actions">
                    <button type="button" class="btn btn-secondary" wire:click="closeAssignModal">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="saveAssign">Enregistrer</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showScheduleModal)
        <div class="crm-modal-backdrop" wire:click.self="closeScheduleModal">
            <div class="crm-modal" role="dialog" aria-modal="true">
                <div class="crm-modal__head">
                    <div>
                        <h3 class="crm-modal__title">Planifier la prochaine action</h3>
                        <p class="crm-modal__sub">{{ $prospect->name }}</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeScheduleModal">Fermer</button>
                </div>
                <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                    <div class="field">
                        <label class="field-label">Type</label>
                        <select class="input" wire:model="scheduleType">
                            @foreach ($actionTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Échéance</label>
                        <input class="input" type="datetime-local" wire:model="scheduleDueAt">
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">Résumé</label>
                        <input class="input" wire:model="scheduleSummary" placeholder="Ex. Rappeler pour devis">
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">Détail</label>
                        <textarea class="input" rows="2" wire:model="scheduleBody"></textarea>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">Assigné à</label>
                        <select class="input" wire:model="scheduleAssigneeId">
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="crm-modal__actions">
                    <button type="button" class="btn btn-secondary" wire:click="closeScheduleModal">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="saveSchedule">Planifier</button>
                </div>
            </div>
        </div>
    @endif
</div>
