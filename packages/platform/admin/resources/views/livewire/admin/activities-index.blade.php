<div class="page-body">
    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Cette semaine</div>
            <div class="dashboard-kpi__value">{{ $kpis['week'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Suivis dus</div>
            <div class="dashboard-kpi__value">{{ $kpis['follow_ups'] }}</div>
            @if ($kpis['follow_ups'] > 0)
                <div class="dashboard-kpi__meta"><a href="{{ route('system.prospects') }}">Voir prospects →</a></div>
            @endif
        </div>
    </section>

    <section class="cc-card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Activités <span style="font-weight:500;color:#64748b;">· {{ $activities->total() }}</span></div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="openLog">Nouvelle activité</button>
        </div>

        @if ($showLog)
            <div class="cc-card__body" style="border-bottom:1px solid #e2e8f0;">
                <div class="form-grid">
                    <div class="field" style="grid-column:1/-1;">
                        <label class="field-label">Prospect</label>
                        <select class="input" wire:model="log_prospect_id">
                            <option value="">— Choisir —</option>
                            @foreach ($openProspects as $p)
                                <option value="{{ $p->id }}">{{ $p->company_name }} · {{ $p->stageLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Type</label>
                        <select class="input" wire:model="log_type">
                            @foreach (['note','call','email','meeting','follow_up'] as $t)
                                <option value="{{ $t }}">{{ $typeLabels[$t] ?? $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Prochain suivi</label>
                        <input class="input" type="date" wire:model="log_follow_up_at">
                    </div>
                    <div class="field">
                        <label class="field-label">Sujet</label>
                        <input class="input" wire:model="log_subject" placeholder="Optionnel">
                    </div>
                    <div class="field" style="grid-column:1/-1;">
                        <label class="field-label">Détail</label>
                        <textarea class="input" rows="3" wire:model="log_body" placeholder="Compte-rendu…"></textarea>
                    </div>
                </div>
                <div class="page-actions" style="margin-top:12px;">
                    <button type="button" class="btn btn-secondary" wire:click="$set('showLog', false)">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="saveLog">Enregistrer</button>
                </div>
            </div>
        @endif

        <div class="form-grid">
            <div class="field">
                <input class="input" type="search" placeholder="Rechercher…" wire:model.live.debounce.300ms="search">
            </div>
            <div class="field">
                <select class="input" wire:model.live="type">
                    <option value="">Tous types</option>
                    @foreach ($typeLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label">Du</label>
                <input class="input" type="date" wire:model.live="from">
            </div>
            <div class="field">
                <label class="field-label">Au</label>
                <input class="input" type="date" wire:model.live="to">
            </div>
        </div>

        <div class="cc-activity-feed">
            @forelse ($activities as $activity)
                <article class="cc-activity-item">
                    <div class="cc-activity-item__dot" data-type="{{ $activity->type }}"></div>
                    <div class="cc-activity-item__body">
                        <div class="cc-activity-item__head">
                            <span class="badge badge-secondary">{{ $typeLabels[$activity->type] ?? $activity->type }}</span>
                            <strong>
                                @if ($activity->prospect)
                                    <a href="{{ route('system.prospects.edit', $activity->prospect) }}">{{ $activity->prospect->company_name }}</a>
                                    @if ($activity->prospect->convertedTenant)
                                        · <a href="{{ route('system.tenants.show', $activity->prospect->convertedTenant) }}">{{ $activity->prospect->convertedTenant->code }}</a>
                                    @endif
                                @else
                                    —
                                @endif
                            </strong>
                            <span class="cc-activity-item__when">
                                {{ $activity->created_at->format('d/m/Y H:i') }}
                                · {{ $activity->user?->name ?? 'Système' }}
                            </span>
                        </div>
                        @if ($activity->subject)
                            <div class="cc-activity-item__subject">{{ $activity->subject }}</div>
                        @endif
                        @if ($activity->body)
                            <div class="cc-activity-item__text">{{ $activity->body }}</div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="stock-empty">Aucune activité.</p>
            @endforelse
        </div>
        @if ($activities->hasPages())
            <div class="table-pagination">
                {{ $activities->links() }}
            </div>
        @endif
    </section>
</div>
