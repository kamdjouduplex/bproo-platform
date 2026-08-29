@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $emoji = [
        'overdue' => '⚠️',
        'urgent' => '🔴',
        'upcoming' => '🟠',
        'planned' => '🟢',
        'paid' => '✓',
    ];
@endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div class="page-actions" style="margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        @if ($canCreate)
            <a class="btn btn-primary" href="{{ route('tenant.treasury.create', ['tenant' => $tenantCode]) }}">Nouvelle dépense prévisionnelle</a>
        @endif
        @if ($canSettings)
            <a class="btn btn-secondary" href="{{ route('tenant.treasury.settings', ['tenant' => $tenantCode]) }}">Seuils d'alerte</a>
        @endif
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;">
        <div class="card" style="padding:14px;margin:0;">
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;">7 jours</div>
            <div style="font-size:1.2rem;font-weight:700;">{{ fmt_money($kpis['due_7']) }}</div>
        </div>
        <div class="card" style="padding:14px;margin:0;">
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;">30 jours</div>
            <div style="font-size:1.2rem;font-weight:700;">{{ fmt_money($kpis['due_30']) }}</div>
        </div>
        <div class="card" style="padding:14px;margin:0;">
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;">60 jours</div>
            <div style="font-size:1.2rem;font-weight:700;">{{ fmt_money($kpis['due_60']) }}</div>
        </div>
        <div class="card" style="padding:14px;margin:0;">
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;">90 jours</div>
            <div style="font-size:1.2rem;font-weight:700;">{{ fmt_money($kpis['due_90']) }}</div>
        </div>
        <div class="card" style="padding:14px;margin:0;">
            <div style="font-size:11px;color:#b91c1c;text-transform:uppercase;">Échéances dépassées</div>
            <div style="font-size:1.2rem;font-weight:700;color:#b91c1c;">{{ $kpis['overdue_count'] }}</div>
            <div style="font-size:11px;color:#94a3b8;">{{ fmt_money($kpis['overdue_amount']) }}</div>
        </div>
        <div class="card" style="padding:14px;margin:0;">
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;">Payées (période)</div>
            <div style="font-size:1.2rem;font-weight:700;">{{ $kpis['paid_count'] }}</div>
        </div>
    </div>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Échéancier des dépenses</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <select class="input input-sm" wire:model.live="direction">
                    <option value="out">À payer</option>
                    <option value="in">À encaisser</option>
                </select>
                <select class="input input-sm" wire:model.live="horizonDays">
                    <option value="7">7 jours</option>
                    <option value="30">30 jours</option>
                    <option value="60">60 jours</option>
                    <option value="90">90 jours</option>
                </select>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Échéance</th>
                        <th>Dépense</th>
                        <th>Montant</th>
                        <th>Jours restants</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="{{ $row['key'] }}" style="{{ $row['paid'] ? 'opacity:.65;' : '' }}">
                            <td>{{ $row['due_date']->format('d/m/Y') }}</td>
                            <td>
                                <strong>{{ $row['label'] }}</strong>
                                <div style="font-size:12px;color:#6b7280;">
                                    {{ $row['category'] }}
                                    @if ($row['beneficiary']) — {{ $row['beneficiary'] }} @endif
                                    @if ($row['frequency'] !== 'once')
                                        · {{ \InovCom\Treasury\Models\TreasuryCommitment::frequencyLabel($row['frequency']) }}
                                    @endif
                                </div>
                            </td>
                            <td><strong>{{ fmt_money($row['amount']) }}</strong></td>
                            <td>
                                @if ($row['urgency']['days'] < 0)
                                    {{ abs($row['urgency']['days']) }} j. de retard
                                @else
                                    {{ $row['urgency']['days'] }} jour{{ $row['urgency']['days'] > 1 ? 's' : '' }}
                                @endif
                            </td>
                            <td>
                                <span style="color:{{ $row['urgency']['color'] }};font-weight:600;">
                                    {{ $emoji[$row['urgency']['key']] ?? '' }} {{ $row['urgency']['label'] }}
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                @if ($row['url'])
                                    <a class="btn btn-secondary btn-sm" href="{{ $row['url'] }}">Voir</a>
                                @endif
                                @if ($canUpdate && $row['editable'] && !$row['paid'])
                                    <button type="button" class="btn btn-secondary btn-sm"
                                            wire:click="markPaid({{ $row['source_id'] }}, '{{ $row['due_date']->toDateString() }}')"
                                            wire:confirm="Marquer cette échéance comme payée ?">Payé</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:#9ca3af;text-align:center;">Aucune échéance sur cette période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p style="padding:12px 16px;font-size:12px;color:#6b7280;margin:0;">
            Seuils : urgent &lt; {{ $thresholds['urgent'] }} j. · à anticiper jusqu’à {{ $thresholds['upcoming'] }} j. · alerte à {{ $thresholds['alert'] }} j.
            Les dettes clients, factures impayées, dépenses non payées et paies traitées alimentent automatiquement l’échéancier.
        </p>
    </section>
</div>
