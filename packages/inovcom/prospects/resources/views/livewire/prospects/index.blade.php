@if (View::exists('inovcom-crm::partials.styles'))
    @include('inovcom-crm::partials.styles')
@endif
@php
    $s = $stats ?? [];
    $inactive = $inactiveCount ?? 0;
    $fmtDue = function ($dt) {
        if (! $dt) {
            return '';
        }
        $time = $dt->format('H:i');
        if ($dt->isToday()) {
            return 'Aujourd’hui, '.$time;
        }
        if ($dt->isTomorrow()) {
            return 'Demain, '.$time;
        }

        return $dt->translatedFormat('j M').', '.$time;
    };
@endphp
<div class="page-body crm-v2">
    @if (session('success'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-error" style="margin-bottom:14px;">{{ session('error') }}</div>@endif

    <div class="crm-v2-head">
        <div>
            <h2>Prospects</h2>
            <p>Gérez tous vos prospects et suivez leur progression.</p>
        </div>
        <div class="crm-v2-actions">
            @if ($canCreate)
                <a class="btn btn-primary" href="{{ route('tenant.prospects.create', ['tenant' => $tenantCode]) }}">+ Nouveau prospect</a>
            @endif
        </div>
    </div>

    <div class="crm-stat-grid">
        <button type="button" class="crm-stat crm-stat--blue" wire:click="setQuickFilter('all')" style="text-align:left;cursor:pointer;border:1px solid var(--crm-line);width:100%;">
            <div class="crm-stat__label">Tous les prospects</div>
            <div class="crm-stat__value">{{ $s['total'] ?? 0 }}</div>
            <span class="crm-stat__bar"></span>
        </button>
        <button type="button" class="crm-stat crm-stat--orange" wire:click="setQuickFilter('nouveau')" style="text-align:left;cursor:pointer;border:1px solid var(--crm-line);width:100%;">
            <div class="crm-stat__label">Nouveaux</div>
            <div class="crm-stat__value">{{ $s['nouveau'] ?? 0 }}</div>
            <span class="crm-stat__bar"></span>
        </button>
        <button type="button" class="crm-stat crm-stat--green" wire:click="setQuickFilter('a_qualifier')" style="text-align:left;cursor:pointer;border:1px solid var(--crm-line);width:100%;">
            <div class="crm-stat__label">À qualifier</div>
            <div class="crm-stat__value">{{ $s['a_qualifier'] ?? 0 }}</div>
            <span class="crm-stat__bar"></span>
        </button>
        <button type="button" class="crm-stat crm-stat--violet" wire:click="setQuickFilter('qualifie')" style="text-align:left;cursor:pointer;border:1px solid var(--crm-line);width:100%;">
            <div class="crm-stat__label">Qualifiés</div>
            <div class="crm-stat__value">{{ $s['qualifie'] ?? 0 }}</div>
            <span class="crm-stat__bar"></span>
        </button>
        <button type="button" class="crm-stat crm-stat--rose" wire:click="setQuickFilter('non_qualifie')" style="text-align:left;cursor:pointer;border:1px solid var(--crm-line);width:100%;">
            <div class="crm-stat__label">Non qualifiés</div>
            <div class="crm-stat__value">{{ $s['non_qualifie'] ?? 0 }}</div>
            <span class="crm-stat__bar"></span>
        </button>
        <button type="button" class="crm-stat crm-stat--cyan" wire:click="setQuickFilter('sans_activite')" style="text-align:left;cursor:pointer;border:1px solid var(--crm-line);width:100%;">
            <div class="crm-stat__label">Sans activité</div>
            <div class="crm-stat__value">{{ $inactive }}</div>
            <span class="crm-stat__bar"></span>
        </button>
    </div>

    <form method="GET" action="{{ url('/app/prospects') }}" class="crm-toolbar" role="search">
        <input type="hidden" name="tenant" value="{{ $tenantCode }}">
        <div class="crm-toolbar__search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
            <input class="input" type="search" name="search" value="{{ $search }}" placeholder="Rechercher un prospect, une entreprise, un téléphone...">
        </div>
        <div class="crm-toolbar__field">
            <label for="crm-filter-status">Statut</label>
            <select id="crm-filter-status" class="input" name="status">
                <option value="all" @selected($statusFilter === 'all')>Tous</option>
                @foreach (\InovCom\Prospects\Models\Prospect::statusOptions() as $v => $l)
                    <option value="{{ $v }}" @selected($statusFilter === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="crm-toolbar__field">
            <label for="crm-filter-source">Source</label>
            <select id="crm-filter-source" class="input" name="source">
                <option value="all" @selected($sourceFilter === 'all')>Toutes</option>
                @foreach (\InovCom\Prospects\Models\Prospect::sourceOptions() as $v => $l)
                    <option value="{{ $v }}" @selected($sourceFilter === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="crm-toolbar__field">
            <label for="crm-filter-owner">Commercial</label>
            <select id="crm-filter-owner" class="input" name="owner">
                <option value="all" @selected($ownerFilter === 'all')>Tous</option>
                @foreach ($owners as $o)
                    <option value="{{ $o->id }}" @selected((string) $ownerFilter === (string) $o->id)>{{ $o->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="crm-toolbar__field">
            <label for="crm-filter-score">Score</label>
            <select id="crm-filter-score" class="input" name="score">
                <option value="all" @selected($scoreFilter === 'all')>Tous</option>
                <option value="chaud" @selected($scoreFilter === 'chaud')>Chaud</option>
                <option value="tiede" @selected($scoreFilter === 'tiede')>Tiède</option>
                <option value="froid" @selected($scoreFilter === 'froid')>Froid</option>
            </select>
        </div>
        <div class="crm-toolbar__actions">
            <button type="submit" class="crm-btn-advanced">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
                Rechercher
            </button>
            <a class="crm-btn-icon" href="{{ route('tenant.prospects.index', ['tenant' => $tenantCode]) }}" title="Réinitialiser" aria-label="Réinitialiser les filtres">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.3"/><polyline points="21 3 21 9 15 9"/></svg>
            </a>
        </div>
    </form>

    <div class="crm-table-wrap crm-table-wrap--fit">
        <table class="crm-table crm-table--fit">
            <thead>
                <tr>
                    <th>Prospect</th>
                    <th>Entreprise</th>
                    <th>Téléphone</th>
                    <th>Source</th>
                    <th>Besoin</th>
                    <th>Score</th>
                    <th>Statut</th>
                    <th>Commercial</th>
                    <th>Dernier contact</th>
                    <th>Prochaine action</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($prospects as $p)
                @php
                    $next = $p->nextPlannedActivity;
                    $temp = $p->temperature();
                    $wa = $p->whatsappNumber();
                    $digits = $wa ? preg_replace('/\D+/', '', $wa) : null;
                    $lastAct = $p->lastCompletedActivity;
                    $nextTone = '';
                    if ($next && $next->due_at) {
                        $nextTone = $next->isOverdue() ? 'is-late' : (($next->isDueToday() || $next->due_at->isTomorrow()) ? 'is-ok' : 'is-soon');
                    } elseif ($next) {
                        $nextTone = 'is-soon';
                    }
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('tenant.prospects.show', ['tenant' => $tenantCode, 'prospect' => $p->id]) }}" class="crm-person" style="text-decoration:none;color:inherit;">
                            <span class="crm-avatar is-solid" style="background:{{ $p->avatarColor() }}"><i>{{ $p->initials() }}</i></span>
                            <div>
                                <strong>{{ $p->contactName() }}</strong>
                                <span>{{ $p->job_title ?: '—' }}</span>
                            </div>
                        </a>
                    </td>
                    <td class="crm-co">{{ $p->companyDisplayName() }}</td>
                    <td>
                        @if ($digits)
                            <a class="crm-phone" href="https://wa.me/{{ $digits }}" target="_blank" rel="noopener">
                                <svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path fill="#25D366" d="M12.04 2C6.58 2 2.15 6.43 2.15 11.89c0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.89-4.43 9.89-9.89C21.94 6.43 17.5 2 12.04 2Zm5.76 14.05c-.24.68-1.4 1.25-1.93 1.33-.49.07-1.12.1-1.81-.11-.42-.13-.95-.31-1.64-.6-2.88-1.25-4.76-4.16-4.91-4.35-.14-.19-1.18-1.57-1.18-3 0-1.42.74-2.12 1.01-2.41.26-.28.58-.35.77-.35h.55c.18 0 .41-.07.64.49.24.58.82 2 .89 2.15.07.14.12.31.02.5-.09.19-.14.31-.28.48-.14.16-.29.37-.42.5-.14.14-.28.29-.12.56.16.28.7 1.16 1.5 1.88 1.04.93 1.91 1.22 2.19 1.36.28.14.44.12.6-.07.16-.19.7-.82.89-1.1.19-.28.37-.23.63-.14.26.09 1.66.78 1.95.93.28.14.47.21.54.33.07.12.07.7-.17 1.38Z"/></svg>
                                {{ $p->phone ?: $wa }}
                            </a>
                        @else
                            {{ $p->phone ?: '—' }}
                        @endif
                    </td>
                    <td>
                        <span class="crm-badge crm-badge--{{ \InovCom\Prospects\Models\Prospect::sourceTone((string) $p->source) }}">
                            {{ \InovCom\Prospects\Models\Prospect::sourceLabel((string) $p->source) }}
                        </span>
                    </td>
                    <td>{{ $p->need ?: $p->product_interest ?: '—' }}</td>
                    <td>
                        <span class="crm-score crm-score--{{ $temp === 'chaud' ? 'hot' : ($temp === 'tiede' ? 'warm' : 'cold') }}">{{ (int) $p->score }}</span>
                    </td>
                    <td>
                        <span class="crm-badge crm-badge--{{ \InovCom\Prospects\Models\Prospect::statusTone((string) $p->status) }}">
                            {{ \InovCom\Prospects\Models\Prospect::statusLabel((string) $p->status) }}
                        </span>
                    </td>
                    <td>
                        <div class="crm-person crm-person--owner">
                            <span class="crm-avatar crm-avatar--sm is-solid" style="background:{{ $p->owner_id ? $p->ownerAvatarColor() : '#94a3b8' }}"><i>{{ $p->ownerInitials() }}</i></span>
                            <span>{{ $p->ownerShortName() }}</span>
                        </div>
                    </td>
                    <td>
                        @if ($p->last_contacted_at)
                            <div class="crm-last">
                                <strong>{{ $p->last_contacted_at->translatedFormat('j M Y') }}</strong>
                                @if ($lastAct)
                                    <span>{{ \InovCom\Prospects\Models\ProspectActivity::typeLabel((string) $lastAct->type) }}</span>
                                @endif
                            </div>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($next)
                            <div class="crm-next {{ $nextTone }}">
                                <strong>{{ $next->displayTitle() }}</strong>
                                @if ($next->due_at)
                                    <time>{{ $fmtDue($next->due_at) }}</time>
                                @endif
                            </div>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <div
                            class="crm-kebab"
                            x-data="{
                                open: false,
                                top: 0,
                                left: 0,
                                _onScroll: null,
                                place() {
                                    const btn = this.$refs.btn;
                                    if (!btn) return;
                                    const r = btn.getBoundingClientRect();
                                    const w = 180;
                                    const h = 140;
                                    let left = r.right - w;
                                    if (left < 8) left = 8;
                                    if (left + w > window.innerWidth - 8) left = window.innerWidth - w - 8;
                                    let top = r.bottom + 6;
                                    if (top + h > window.innerHeight - 8) top = r.top - h - 6;
                                    if (top < 8) top = 8;
                                    this.top = top;
                                    this.left = left;
                                },
                                listenScroll() {
                                    this.unlistenScroll();
                                    this._onScroll = () => { this.open = false; this.unlistenScroll(); };
                                    document.addEventListener('scroll', this._onScroll, true);
                                },
                                unlistenScroll() {
                                    if (this._onScroll) {
                                        document.removeEventListener('scroll', this._onScroll, true);
                                        this._onScroll = null;
                                    }
                                },
                                toggle() {
                                    this.open = ! this.open;
                                    if (this.open) {
                                        this.$nextTick(() => this.place());
                                        this.listenScroll();
                                    } else {
                                        this.unlistenScroll();
                                    }
                                }
                            }"
                        >
                            <button type="button" class="crm-kebab__btn" x-ref="btn" @click.stop="toggle()" aria-label="Actions">⋯</button>
                            <template x-teleport="body">
                                <div
                                    class="crm-kebab__menu crm-kebab__menu--portal"
                                    x-show="open"
                                    x-cloak
                                    @click.outside="open = false; unlistenScroll()"
                                    @keydown.escape.window="open = false; unlistenScroll()"
                                    :style="'top:' + top + 'px;left:' + left + 'px'"
                                >
                                    <a href="{{ route('tenant.prospects.show', ['tenant' => $tenantCode, 'prospect' => $p->id]) }}">Voir la fiche</a>
                                    @if ($canUpdate)
                                        <a href="{{ route('tenant.prospects.edit', ['tenant' => $tenantCode, 'prospect' => $p->id]) }}">Modifier</a>
                                    @endif
                                    @if ($canDelete)
                                        <button type="button" class="is-danger" @click="if (confirm('Supprimer ce prospect ?')) { open = false; $wire.delete({{ $p->id }}) }">Supprimer</button>
                                    @endif
                                </div>
                            </template>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="crm-empty">Aucun prospect. Capturez le premier en 30 secondes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <div>{{ $prospects->links() }}</div>
        <select class="input" wire:model.live="perPage" style="width:auto;">
            <option value="10">10 / page</option>
            <option value="20">20 / page</option>
            <option value="50">50 / page</option>
        </select>
    </div>
</div>
