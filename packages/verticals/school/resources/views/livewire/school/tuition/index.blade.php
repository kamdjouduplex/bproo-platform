<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card" style="margin-bottom:14px;">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Soldes scolarité</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="syncFeesForVisible" wire:confirm="Imputer les structures de frais manquantes pour l’année / classe filtrées ?">
                    Imputer les frais
                </button>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.reports.print', ['tenant' => $tenantCode, 'type' => 'debtors', 'year' => $filterYearId, 'class' => $filterClassId]) }}" target="_blank">PDF débiteurs</a>
                <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.payments.index', ['tenant' => $tenantCode]) }}">Nouveau paiement</a>
            </div>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:10px; padding:0 16px 14px;">
            <div style="padding:10px 14px; border:1px solid #bbf7d0; background:#f0fdf4; border-radius:8px; min-width:120px;">
                <div style="font-size:11px; color:#166534;">À jour</div>
                <div style="font-size:20px; font-weight:700;">{{ $counts['paid'] }}</div>
            </div>
            <div style="padding:10px 14px; border:1px solid #fde68a; background:#fffbeb; border-radius:8px; min-width:120px;">
                <div style="font-size:11px; color:#92400e;">Partiel</div>
                <div style="font-size:20px; font-weight:700;">{{ $counts['partial'] }}</div>
            </div>
            <div style="padding:10px 14px; border:1px solid #fecaca; background:#fef2f2; border-radius:8px; min-width:120px;">
                <div style="font-size:11px; color:#991b1b;">Impayé</div>
                <div style="font-size:20px; font-weight:700;">{{ $counts['unpaid'] }}</div>
            </div>
            <div style="padding:10px 14px; border:1px solid #99f6e4; background:#f0fdfa; border-radius:8px; min-width:160px;">
                <div style="font-size:11px; color:#0f766e;">Solde déjà perçu</div>
                <div style="font-size:20px; font-weight:700;">{{ number_format($counts['paid_total'], 0, ',', ' ') }}</div>
                @if(($counts['charged_total'] ?? 0) > 0)
                    <div style="font-size:11px; color:#64748b; margin-top:2px;">sur {{ number_format($counts['charged_total'], 0, ',', ' ') }} imputés</div>
                @endif
            </div>
            <div style="padding:10px 14px; border:1px solid #e2e8f0; background:#f8fafc; border-radius:8px; min-width:160px;">
                <div style="font-size:11px; color:#475569;">Reste à recouvrer</div>
                <div style="font-size:20px; font-weight:700;">{{ number_format($counts['due_total'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </section>

    <section class="card app-table-card">
        <div class="sch-filters" style="flex-wrap:wrap;">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Élève, parent, téléphone…">
            <select class="input" wire:model.live="filterYearId" style="max-width:200px;">
                <option value="">Toutes années</option>
                @foreach($years as $y)
                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="filterClassId" style="max-width:160px;">
                <option value="">Toutes classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="filterStatus" style="max-width:160px;">
                <option value="">Tous soldes</option>
                <option value="paid">À jour (soldé)</option>
                <option value="partial">Partiel</option>
                <option value="unpaid">Impayé</option>
                <option value="none">Sans frais</option>
            </select>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th>Classe</th>
                        <th>Parent</th>
                        <th class="right">Frais</th>
                        <th class="right">Payé</th>
                        <th class="right">Reste</th>
                        <th>Statut</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $s = $row['student'];
                            $e = $row['enrollment'];
                            $badge = match($row['status']) {
                                'paid' => ['À jour', 'badge-success'],
                                'partial' => ['Partiel', 'badge-info'],
                                'unpaid' => ['Impayé', 'badge-danger'],
                                default => ['Sans frais', 'badge-secondary'],
                            };
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $s?->student_code }}</strong>
                                — {{ $s?->full_name }}
                            </td>
                            <td>{{ $e->schoolClass?->name ?? '—' }}@if($e->section) <span style="color:#94a3b8;">({{ $e->section }})</span>@endif</td>
                            <td>
                                {{ $s?->parent_full_name ?? '—' }}
                                @if($s?->parent_phone)<div style="font-size:11px;color:#64748b;">{{ $s->parent_phone }}</div>@endif
                            </td>
                            <td class="right">{{ number_format($row['charged'], 0, ',', ' ') }}</td>
                            <td class="right">{{ number_format($row['paid'], 0, ',', ' ') }}</td>
                            <td class="right"><strong>{{ number_format($row['due'], 0, ',', ' ') }}</strong></td>
                            <td><span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    @if($s)
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.students.show', ['tenant' => $tenantCode, 'id' => $s->id]) }}">Fiche</a>
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.payments.index', ['tenant' => $tenantCode]) }}">Payer</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Aucun élève pour ces filtres. Vérifiez l’année et cliquez « Imputer les frais » si besoin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $rows->links() }}</div>
    </section>
</div>
