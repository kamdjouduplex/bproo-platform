<div class="page-body caisse-page">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif

    @if (!$schemaReady)
        <div class="alert alert-error" style="margin-bottom: 16px;">
            Le module Caisse est activé mais ses tables ne sont pas encore migrées pour ce tenant.
            Exécutez la migration tenant puis rechargez la page.
        </div>
    @endif

    @if ($schemaReady && $sessionOverdue)
        <div class="alert alert-error caisse-page__alert">
            La session <strong>{{ $activeSession?->session_number }}</strong> est encore ouverte depuis le
            {{ $activeSession?->opened_at?->format('d/m/Y') }}.
            Clôturez-la avant d'ouvrir une nouvelle session.
        </div>
    @endif

    <section class="card caisse-page__card">
        <header class="caisse-page__header">
            <div class="caisse-page__status {{ $activeSession ? 'caisse-page__status--open' : 'caisse-page__status--closed' }}">
                <div class="caisse-page__status-icon" aria-hidden="true">
                    @if ($activeSession)
                        <span class="caisse-page__status-dot"></span>
                    @endif
                </div>
                <div>
                    <div class="caisse-page__status-label">
                        {{ $activeSession ? 'Caisse ouverte' : 'Caisse fermée' }}
                    </div>
                    <div class="caisse-page__status-detail">
                        @if ($activeSession)
                            {{ $activeSession->session_number }}
                            · depuis {{ $activeSession->opened_at?->format('d/m/Y H:i') }}
                        @else
                            Ouvrez la caisse avec le fond de caisse initial pour démarrer la session.
                        @endif
                    </div>
                </div>
                <div class="caisse-page__balance">
                    <span class="caisse-page__balance-label">Solde en caisse</span>
                    <span class="caisse-page__balance-value">{{ fmt_money($balance) }} <small>FCFA</small></span>
                </div>
            </div>

            <div class="caisse-page__exports">
                <span class="caisse-page__exports-label">États & exports</span>
                <div class="caisse-page__exports-btns">
                    @if ($activeSession && $canView)
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="exportActiveSessionPdf">PDF session</button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="exportActiveSessionExcel">Excel session</button>
                    @endif
                    @if ($canView)
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="exportJournalPdf" @disabled(!$schemaReady)>PDF journal</button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="exportJournalExcel" @disabled(!$schemaReady)>Excel journal</button>
                    @endif
                </div>
            </div>
        </header>

        <div class="caisse-tabs">
            <div class="caisse-tabs__head">
                <button type="button" class="caisse-tabs__tab {{ $activeTab === 'register' ? 'caisse-tabs__tab--active' : '' }}" wire:click="setTab('register')">Caisse</button>
                <button type="button" class="caisse-tabs__tab {{ $activeTab === 'history' ? 'caisse-tabs__tab--active' : '' }}" wire:click="setTab('history')">Journal</button>
                <button type="button" class="caisse-tabs__tab {{ $activeTab === 'sessions' ? 'caisse-tabs__tab--active' : '' }}" wire:click="setTab('sessions')">Sessions</button>
            </div>

            <div class="caisse-tabs__panel">
                @if ($activeTab === 'register')
                    <div class="caisse-workflow">
                        <div class="caisse-workflow__step {{ !$activeSession ? 'caisse-workflow__step--current' : 'caisse-workflow__step--done' }}">
                            <span class="caisse-workflow__num">1</span>
                            <span>Ouverture</span>
                        </div>
                        <div class="caisse-workflow__line"></div>
                        <div class="caisse-workflow__step {{ $activeSession ? 'caisse-workflow__step--current' : '' }}">
                            <span class="caisse-workflow__num">2</span>
                            <span>Mouvements (auto + manuel)</span>
                        </div>
                        <div class="caisse-workflow__line"></div>
                        <div class="caisse-workflow__step">
                            <span class="caisse-workflow__num">3</span>
                            <span>Clôture</span>
                        </div>
                    </div>

                    @if ($activeSession && $sessionSummary)
                        <div class="caisse-kpis" style="margin-bottom: 20px;">
                            <article class="caisse-kpi">
                                <div class="caisse-kpi__label">Fond initial</div>
                                <div class="caisse-kpi__value caisse-kpi__value--sm">{{ fmt_money($sessionSummary['opening_amount']) }}</div>
                            </article>
                            <article class="caisse-kpi">
                                <div class="caisse-kpi__label">Entrées</div>
                                <div class="caisse-kpi__value caisse-kpi__value--sm" style="color:#16a34a;">+{{ fmt_money($sessionSummary['total_in']) }}</div>
                            </article>
                            <article class="caisse-kpi">
                                <div class="caisse-kpi__label">Sorties</div>
                                <div class="caisse-kpi__value caisse-kpi__value--sm" style="color:#dc2626;">−{{ fmt_money($sessionSummary['total_out']) }}</div>
                            </article>
                            <article class="caisse-kpi caisse-kpi--primary">
                                <div class="caisse-kpi__label">Solde théorique</div>
                                <div class="caisse-kpi__value caisse-kpi__value--sm">{{ fmt_money($sessionSummary['expected_balance']) }}</div>
                            </article>
                        </div>
                    @endif

                    <div class="caisse-ops-layout">
                        @if (!$activeSession)
                            <section class="caisse-ops-card caisse-ops-card--open">
                                <h3 class="caisse-ops-card__title">Ouvrir la caisse</h3>
                                <p class="caisse-ops-card__hint">
                                    Comptez le fond de caisse physique et saisissez le montant initial.
                                    Les encaissements et décaissements en espèces (ventes, factures, dettes,
                                    dépenses, avoirs) sont ensuite enregistrés automatiquement dans le journal.
                                </p>
                                <div class="field">
                                    <label class="field-label" for="opening-amount">Fond de caisse (FCFA)</label>
                                    <input id="opening-amount" class="input" type="number" min="0" step="0.01" wire:model="openingAmount">
                                    @error('openingAmount') <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                                <div class="field" style="margin-top:10px;">
                                    <label class="field-label" for="opening-note">Note (optionnel)</label>
                                    <input id="opening-note" class="input" type="text" wire:model="openingNote" placeholder="Ex. Billets + pièces du tiroir">
                                </div>
                                <button class="btn btn-primary" wire:click="openSession" style="margin-top:14px;" @disabled(!$schemaReady || !$canOpen) wire:loading.attr="disabled" wire:target="openSession">
                                    <span wire:loading.remove wire:target="openSession">Ouvrir la caisse</span>
                                    <span wire:loading wire:target="openSession">Ouverture…</span>
                                </button>
                            </section>
                        @else
                            <section class="caisse-ops-card">
                                <h3 class="caisse-ops-card__title">Entrée manuelle</h3>
                                <p class="caisse-ops-card__hint">Encaissement espèces, apport, remboursement reçu, etc.</p>
                                <div class="field">
                                    <label class="field-label">Montant (FCFA)</label>
                                    <input class="input" type="number" min="0.01" step="0.01" wire:model="cashInAmount" @disabled(!$canCashIn)>
                                    @error('cashInAmount') <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                                <div class="field" style="margin-top:8px;">
                                    <label class="field-label">Motif</label>
                                    <input class="input" type="text" wire:model="cashInReason" placeholder="Ex. Encaissement vente #123" @disabled(!$canCashIn)>
                                    @error('cashInReason') <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                                <div class="field" style="margin-top:8px;">
                                    <label class="field-label">Référence</label>
                                    <input class="input" type="text" wire:model="cashInReference" placeholder="N° reçu, facture, ticket…" @disabled(!$canCashIn)>
                                    @error('cashInReference') <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                                <div style="display:flex;gap:8px;margin-top:12px;">
                                    <button class="btn btn-success" wire:click="addCashIn" @disabled(!$canCashIn) wire:loading.attr="disabled" wire:target="addCashIn">Enregistrer entrée</button>
                                    <button class="btn btn-secondary" type="button" wire:click="resetCashInForm" @disabled(!$canCashIn)>Effacer</button>
                                </div>
                            </section>

                            <section class="caisse-ops-card">
                                <h3 class="caisse-ops-card__title">Sortie manuelle</h3>
                                <p class="caisse-ops-card__hint">Dépense payée, remboursement client, retrait, etc.</p>
                                <div class="field">
                                    <label class="field-label">Montant (FCFA)</label>
                                    <input class="input" type="number" min="0.01" step="0.01" wire:model="cashOutAmount" @disabled(!$canCashOut)>
                                    @error('cashOutAmount') <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                                <div class="field" style="margin-top:8px;">
                                    <label class="field-label">Motif</label>
                                    <input class="input" type="text" wire:model="cashOutReason" placeholder="Ex. Achat fournitures" @disabled(!$canCashOut)>
                                    @error('cashOutReason') <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                                <div class="field" style="margin-top:8px;">
                                    <label class="field-label">Référence</label>
                                    <input class="input" type="text" wire:model="cashOutReference" placeholder="N° pièce, facture…" @disabled(!$canCashOut)>
                                    @error('cashOutReference') <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                                <div style="display:flex;gap:8px;margin-top:12px;">
                                    <button class="btn btn-error" wire:click="addCashOut" @disabled(!$canCashOut) wire:loading.attr="disabled" wire:target="addCashOut">Enregistrer sortie</button>
                                    <button class="btn btn-secondary" type="button" wire:click="resetCashOutForm" @disabled(!$canCashOut)>Effacer</button>
                                </div>
                            </section>

                            <section class="caisse-ops-card caisse-ops-card--close">
                                <h3 class="caisse-ops-card__title">Clôturer la caisse</h3>
                                <p class="caisse-ops-card__hint">
                                    Comptez le contenu du tiroir. Le solde théorique est
                                    <strong>{{ fmt_money($balance) }} FCFA</strong>.
                                </p>
                                <div class="field">
                                    <label class="field-label">Montant compté (FCFA)</label>
                                    <input class="input" type="number" min="0" step="0.01" wire:model="closeCountedAmount" @disabled(!$canClose)>
                                    @error('closeCountedAmount') <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                                <div class="field" style="margin-top:8px;">
                                    <label class="field-label">Note de clôture</label>
                                    <input class="input" type="text" wire:model="closeNote" placeholder="Écart, remarques…" @disabled(!$canClose)>
                                </div>
                                <button class="btn btn-secondary" wire:click="closeSession" style="margin-top:12px;" @disabled(!$canClose) wire:loading.attr="disabled" wire:target="closeSession">
                                    <span wire:loading.remove wire:target="closeSession">Clôturer la session</span>
                                    <span wire:loading wire:target="closeSession">Clôture…</span>
                                </button>
                            </section>
                        @endif
                    </div>

                    @if ($activeSession && $todayEntries->count() > 0)
                        <h3 class="caisse-section-title">Mouvements de la session en cours</h3>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Heure</th>
                                        <th>Origine</th>
                                        <th>Type</th>
                                        <th>Motif</th>
                                        <th>Réf.</th>
                                        <th>Entrée</th>
                                        <th>Sortie</th>
                                        <th>Solde</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($todayEntries->sortByDesc('id') as $entry)
                                        <tr>
                                            <td>{{ $entry->entry_date?->format('H:i') }}</td>
                                            <td><span class="badge badge-secondary">{{ $entry->source_label }}</span></td>
                                            <td>{{ $entry->type_label }}</td>
                                            <td>{{ $entry->reason }}</td>
                                            <td>{{ $entry->reference_number ?? '—' }}</td>
                                            <td>@if ($entry->direction === 'in'){{ fmt_money($entry->amount) }}@else — @endif</td>
                                            <td>@if ($entry->direction === 'out'){{ fmt_money($entry->amount) }}@else — @endif</td>
                                            <td><strong>{{ fmt_money($entry->balance_after) }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                @if ($activeTab === 'history')
                    <h2 class="card-title">Journal des mouvements</h2>
                    <p class="caisse-ops-card__hint" style="margin-top:0;">
                        Tous les mouvements espèces : encaissements et décaissements automatiques
                        (ventes, factures, dettes, dépenses, avoirs) + saisies manuelles. Exportez en PDF ou Excel à tout moment.
                    </p>

                    <div class="caisse-kpis" style="margin:12px 0 4px;">
                        <article class="caisse-kpi">
                            <div class="caisse-kpi__label">Entrées (période)</div>
                            <div class="caisse-kpi__value caisse-kpi__value--sm" style="color:#16a34a;">+{{ fmt_money($periodTotals['in']) }}</div>
                        </article>
                        <article class="caisse-kpi">
                            <div class="caisse-kpi__label">Sorties (période)</div>
                            <div class="caisse-kpi__value caisse-kpi__value--sm" style="color:#dc2626;">−{{ fmt_money($periodTotals['out']) }}</div>
                        </article>
                        <article class="caisse-kpi caisse-kpi--primary">
                            <div class="caisse-kpi__label">Net (période)</div>
                            <div class="caisse-kpi__value caisse-kpi__value--sm">{{ fmt_money($periodTotals['in'] - $periodTotals['out']) }}</div>
                        </article>
                    </div>

                    <form class="caisse-filters" wire:submit.prevent="applyFilters">
                        <div class="caisse-filters__row">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('today')">Aujourd'hui</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('week')">Semaine</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="setPeriod('month')">Mois</button>
                            <input class="input input-sm" type="date" wire:model="entryDateFrom" aria-label="Du">
                            <input class="input input-sm" type="date" wire:model="entryDateTo" aria-label="Au">
                            <select class="input input-sm" wire:model="sourceFilter" aria-label="Origine">
                                <option value="all">Toutes origines</option>
                                @foreach ($sourceOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input class="input input-sm" type="search" wire:model="search" placeholder="Motif ou référence…">
                            <select class="input input-sm" wire:model="perPage">
                                <option value="30">30</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm">Filtrer</button>
                        </div>
                    </form>

                    <div class="table-scroll" style="margin-top:16px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Origine</th>
                                    <th>Session</th>
                                    <th>Type</th>
                                    <th>Motif</th>
                                    <th>Référence</th>
                                    <th>Entrée</th>
                                    <th>Sortie</th>
                                    <th>Solde</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $entry)
                                    <tr>
                                        <td>{{ $entry->entry_date?->format('d/m/Y H:i') }}</td>
                                        <td><span class="badge badge-secondary">{{ $entry->source_label }}</span></td>
                                        <td>{{ $entry->session?->session_number ?? '—' }}</td>
                                        <td>{{ $entry->type_label }}</td>
                                        <td>{{ $entry->reason }}</td>
                                        <td>{{ $entry->reference_number ?? '—' }}</td>
                                        <td>@if ($entry->direction === 'in'){{ fmt_money($entry->amount) }}@else — @endif</td>
                                        <td>@if ($entry->direction === 'out'){{ fmt_money($entry->amount) }}@else — @endif</td>
                                        <td><strong>{{ fmt_money($entry->balance_after) }}</strong></td>
                                    </tr>
                                @endforeach
                                @if ($entries->count() === 0)
                                    <tr><td colspan="9">Aucun mouvement pour cette période.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    @if ($entries->hasPages())
                        <div class="table-pagination">{{ $entries->links() }}</div>
                    @endif
                @endif

                @if ($activeTab === 'sessions')
                    <h2 class="card-title">Sessions de caisse</h2>
                    <p class="caisse-ops-card__hint" style="margin-top:0;">
                        Chaque session correspond à une ouverture / clôture de caisse physique.
                        Téléchargez l'état PDF ou Excel pour archivage ou contrôle.
                    </p>

                    <div class="table-scroll" style="margin-top:16px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Session</th>
                                    <th>Ouverture</th>
                                    <th>Clôture</th>
                                    <th>Fond</th>
                                    <th>Théorique</th>
                                    <th>Compté</th>
                                    <th>Écart</th>
                                    <th>Statut</th>
                                    <th>Exports</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sessions as $session)
                                    @php
                                        $variance = $session->closing_amount_counted !== null && $session->closing_amount_expected !== null
                                            ? (float) $session->closing_amount_counted - (float) $session->closing_amount_expected
                                            : null;
                                        $isOverdue = $session->status === 'open' && $session->opened_at && $session->opened_at->toDateString() < now()->toDateString();
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $session->session_number }}</strong></td>
                                        <td>{{ $session->opened_at?->format('d/m/Y H:i') }}</td>
                                        <td>{{ $session->closed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                        <td>{{ fmt_money((float) $session->opening_amount) }}</td>
                                        <td>{{ $session->closing_amount_expected !== null ? fmt_money((float) $session->closing_amount_expected) : '—' }}</td>
                                        <td>{{ $session->closing_amount_counted !== null ? fmt_money((float) $session->closing_amount_counted) : '—' }}</td>
                                        <td>
                                            @if ($variance !== null)
                                                <span class="{{ abs($variance) < 0.01 ? 'text-success' : 'text-danger' }}">{{ fmt_money($variance) }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if ($session->status === 'open' && $isOverdue)
                                                <span class="badge badge-error">Ouverte (retard)</span>
                                            @elseif ($session->status === 'open')
                                                <span class="badge badge-warning">Ouverte</span>
                                            @else
                                                <span class="badge badge-success">Clôturée</span>
                                            @endif
                                            @if ($session->status === 'closed' && $session->opened_at?->toDateString() === now()->toDateString())
                                                <button type="button" class="btn btn-secondary btn-sm" style="margin-left:6px;" wire:click="reopenSession({{ $session->id }})" @disabled(!$canOpen)>Rouvrir</button>
                                            @endif
                                        </td>
                                        <td class="caisse-export-cell">
                                            @if ($canView)
                                                <button type="button" class="btn btn-export btn-export--pdf btn-sm" wire:click="exportSessionPdf({{ $session->id }})">
                                                    <x-file-type-icon format="pdf" class="btn-export__glyph" />
                                                    <span class="btn-export__label">PDF</span>
                                                </button>
                                                <button type="button" class="btn btn-export btn-export--excel btn-sm" wire:click="exportSessionExcel({{ $session->id }})">
                                                    <x-file-type-icon format="excel" class="btn-export__glyph" />
                                                    <span class="btn-export__label">Excel</span>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($sessions->count() === 0)
                                    <tr><td colspan="9">Aucune session enregistrée.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
