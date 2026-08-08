@php
    use InovCom\Prospects\Models\Prospect;
    use InovCom\Prospects\Models\ProspectActivity;

    $ownerName = $prospect->owner?->name ?? 'Non assigné';
    $initials = collect(preg_split('/\s+/', trim($ownerName)))
        ->filter()
        ->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('') ?: '?';
    $next = $prospect->relationLoaded('nextPlannedActivity')
        ? $prospect->nextPlannedActivity
        : $prospect->nextPlannedActivity()->first();
    $activities = $prospect->relationLoaded('activities')
        ? $prospect->activities
        : $prospect->activities()->with(['user', 'assignee'])->limit(12)->get();
    $nextTone = $next
        ? ($next->isOverdue() ? 'danger' : ($next->isDueToday() ? 'today' : 'ok'))
        : null;
    $isHot = $next && ($next->isDueToday() || $next->isOverdue());
    $canAct = $canManage ?? false;
    $canConvertPanel = $canConvert ?? false;
    $compact = $compact ?? false;
@endphp

<div class="crm-drawer {{ $compact ? 'crm-drawer--page' : '' }}">
    <header class="crm-drawer__head">
        <div class="crm-drawer__head-main">
            <h2 class="crm-drawer__title">{{ $prospect->name }}</h2>
            <div class="crm-drawer__badges">
                <span class="crm-pill crm-pill--{{ $prospect->status }}">{{ Prospect::statusLabel($prospect->status) }}</span>
                @if ($isHot)
                    <span class="crm-pill crm-pill--hot">Hot prospect</span>
                @endif
            </div>
        </div>
        @if (! $compact)
            <button type="button" class="crm-drawer__close" wire:click="closePanel" aria-label="Fermer">×</button>
        @endif
    </header>

    <section class="crm-drawer__section">
        <h3 class="crm-drawer__label">Contact principal</h3>
        <ul class="crm-drawer__list">
            <li>
                <span class="crm-drawer__icon" aria-hidden="true">👤</span>
                <span>{{ $prospect->name }}</span>
            </li>
            <li>
                <span class="crm-drawer__icon" aria-hidden="true">☎</span>
                @if ($prospect->phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $prospect->phone) }}">{{ $prospect->phone }}</a>
                @else
                    <span class="crm-muted">Aucun téléphone</span>
                @endif
            </li>
            <li>
                <span class="crm-drawer__icon" aria-hidden="true">✉</span>
                @if ($prospect->email)
                    <a href="mailto:{{ $prospect->email }}">{{ $prospect->email }}</a>
                @else
                    <span class="crm-muted">Aucun e-mail</span>
                @endif
            </li>
        </ul>
    </section>

    <section class="crm-drawer__section">
        <h3 class="crm-drawer__label">Informations entreprise</h3>
        <dl class="crm-drawer__grid">
            <div>
                <dt>Référence</dt>
                <dd>{{ $prospect->reference }}</dd>
            </div>
            <div>
                <dt>Type</dt>
                <dd>
                    <span class="crm-pill crm-pill--soft">{{ Prospect::typeOptions()[$prospect->type] ?? $prospect->type }}</span>
                </dd>
            </div>
            <div>
                <dt>Source</dt>
                <dd>{{ Prospect::sourceLabel($prospect->source) }}</dd>
            </div>
            <div>
                <dt>CA estimé</dt>
                <dd class="crm-drawer__money">{{ $prospect->expected_value !== null ? number_format((float) $prospect->expected_value, 0, ',', ' ') : '—' }}</dd>
            </div>
            <div>
                <dt>Localisation</dt>
                <dd>{{ $prospect->address ?: '—' }}</dd>
            </div>
            <div>
                <dt>Assigné à</dt>
                <dd class="crm-drawer__assignee">
                    <span class="crm-avatar crm-avatar--sm">{{ $initials }}</span>
                    {{ $ownerName }}
                </dd>
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
            @endif
        </dl>
    </section>

    @if ($next)
        <section class="crm-drawer__next crm-drawer__next--{{ $nextTone }}">
            <h3 class="crm-drawer__label">Prochaine action</h3>
            <div class="crm-drawer__next-body">
                <div class="crm-drawer__next-icon" aria-hidden="true">📅</div>
                <div>
                    <strong>{{ $next->displayTitle() }}</strong>
                    <p>
                        {{ $next->due_at?->translatedFormat('l d/m · H:i') ?? 'Sans échéance' }}
                        @if ($next->assignee)
                            · {{ $next->assignee->name }}
                        @endif
                    </p>
                </div>
            </div>
            @if ($canAct)
                <div class="crm-drawer__next-actions">
                    <button type="button" class="crm-card__btn crm-card__btn--primary" wire:click="completeNextAction({{ $next->id }})">Marquer fait</button>
                    <button type="button" class="crm-card__btn" wire:click="openScheduleModal({{ $prospect->id }})">Replanifier</button>
                </div>
            @endif
        </section>
    @elseif ($canAct && ! $prospect->isLost() && ! $prospect->isConverted())
        <section class="crm-drawer__section">
            <button type="button" class="crm-card__btn" style="width:100%" wire:click="openScheduleModal({{ $prospect->id }})">+ Planifier une action</button>
        </section>
    @endif

    <section class="crm-drawer__section">
        <h3 class="crm-drawer__label">Historique récent</h3>
        <ol class="crm-timeline">
            @forelse ($activities as $activity)
                @php
                    $dot = match ($activity->type) {
                        'call' => 'call',
                        'email' => 'email',
                        'meeting', 'reunion' => 'meeting',
                        'demo' => 'demo',
                        'presentation' => 'presentation',
                        'task' => 'task',
                        'status' => 'status',
                        default => 'note',
                    };
                @endphp
                <li class="crm-timeline__item">
                    <span class="crm-timeline__dot crm-timeline__dot--{{ $dot }}"></span>
                    <div>
                        <strong>
                            @if (($activity->state ?? '') === 'planned')
                                Planifié · {{ ProspectActivity::typeLabel($activity->type) }}
                            @else
                                {{ $activity->displayTitle() }}
                            @endif
                        </strong>
                        <p class="crm-muted">
                            {{ ($activity->due_at ?? $activity->completed_at ?? $activity->created_at)?->format('d/m/Y H:i') }}
                            · {{ $activity->assignee?->name ?? $activity->user?->name ?? '—' }}
                        </p>
                        @if ($activity->body && $activity->body !== $activity->displayTitle())
                            <p class="crm-timeline__text">{{ \Illuminate\Support\Str::limit($activity->body, 140) }}</p>
                        @endif
                    </div>
                </li>
            @empty
                <li class="crm-muted" style="list-style:none;padding-left:0;">Aucune activité pour l’instant.</li>
            @endforelse
        </ol>
    </section>

    <footer class="crm-drawer__footer">
        @if (Route::has('tenant.prospects.show') && ! $compact)
            <a class="crm-card__btn" href="{{ route('tenant.prospects.show', $prospect) }}">Voir la fiche complète</a>
        @elseif (Route::has('tenant.prospects.edit') && $compact && ($canUpdate ?? false))
            <a class="crm-card__btn" href="{{ route('tenant.prospects.edit', $prospect) }}">Modifier</a>
        @endif

        @if ($canAct && ! $prospect->isConverted())
            <div class="crm-drawer__actions-wrap">
                <button type="button" class="crm-card__btn crm-card__btn--accent" wire:click="togglePanelActions">
                    Actions ▾
                </button>
                @if ($showPanelActions ?? false)
                    <div class="crm-drawer__menu">
                        @if ($prospect->status === Prospect::STATUS_QUALIFIE)
                            <button type="button" wire:click="advance({{ $prospect->id }})">Passer en négociation</button>
                        @endif
                        @if ($prospect->status === Prospect::STATUS_NEGOCIATION)
                            <button type="button" wire:click="advance({{ $prospect->id }})">Marquer gagné</button>
                        @endif
                        @if ($prospect->status === Prospect::STATUS_GAGNE && $canConvertPanel)
                            <button type="button" wire:click="convert({{ $prospect->id }})" wire:confirm="Convertir en client ?">Convertir en client</button>
                        @endif
                        @if (in_array($prospect->status, [Prospect::STATUS_NOUVEAU, Prospect::STATUS_CONTACTE], true) && $prospect->isReadyToInitiate())
                            <button type="button" wire:click="initiate({{ $prospect->id }})">Placer en Qualifié</button>
                        @endif
                        <button type="button" wire:click="openScheduleModal({{ $prospect->id }})">Planifier</button>
                        <button type="button" wire:click="openAssignModal({{ $prospect->id }})">Assigner</button>
                        <button type="button" wire:click="logQuickActivity({{ $prospect->id }}, 'call')">Logger un appel</button>
                        <button type="button" wire:click="logQuickActivity({{ $prospect->id }}, 'email')">Logger un e-mail</button>
                        @if (! $prospect->isLost())
                            <button type="button" class="is-danger" wire:click="markLost({{ $prospect->id }})" wire:confirm="Marquer perdu ?">Marquer perdu</button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </footer>
</div>
