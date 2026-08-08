@php
    use InovCom\Payroll\Support\EmployeeRules;
    $tenantCode = $tenantCode ?? request()->query('tenant');
@endphp

<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <section class="card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
            <div>
                <h2 style="margin:0 0 4px;">{{ $employee->full_name }}</h2>
                <p style="margin:0; color:#6b7280; font-size:14px;">
                    {{ $employee->employee_number }}
                    @if ($employee->position) · {{ $employee->position }} @endif
                    @if ($employee->department) · {{ $employee->department }} @endif
                </p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @if ($employee->user_id && \Illuminate\Support\Facades\Route::has('tenant.users.edit'))
                    <a class="btn btn-secondary" href="{{ route('tenant.users.edit', [$employee->user_id, 'tenant' => $tenantCode]) }}">Modifier l’utilisateur</a>
                @elseif (\Illuminate\Support\Facades\Route::has('tenant.users.index'))
                    <a class="btn btn-secondary" href="{{ route('tenant.users.index', ['tenant' => $tenantCode]) }}">Utilisateurs</a>
                @endif
                @if (\Illuminate\Support\Facades\Route::has('tenant.attendance.sheet'))
                    <a class="btn btn-secondary" href="{{ route('tenant.attendance.sheet', ['tenant' => $tenantCode, 'employee_id' => $employee->id]) }}">Présence</a>
                @endif
                @if ($canLeave && \Illuminate\Support\Facades\Route::has('tenant.payroll.leaves.index'))
                    <a class="btn btn-secondary" href="{{ route('tenant.payroll.leaves.index', ['tenant' => $tenantCode, 'employee_id' => $employee->id]) }}">Congés</a>
                @endif
                <a class="btn btn-secondary" href="{{ route('tenant.payroll.index', ['tenant' => $tenantCode]) }}">← Paie</a>
            </div>
        </div>
    </section>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:16px;">
        <div class="card" style="padding:16px;">
            <div style="font-size:12px; color:#6b7280;">Salaire actuel</div>
            <strong style="font-size:20px;">{{ fmt_money($employee->base_salary) }} FCFA</strong>
        </div>
        <div class="card" style="padding:16px;">
            <div style="font-size:12px; color:#6b7280;">Contrat</div>
            <strong>{{ EmployeeRules::contractTypeLabel($employee->contract_type) }}</strong>
        </div>
        <div class="card" style="padding:16px;">
            <div style="font-size:12px; color:#6b7280;">Compte système</div>
            <strong>{{ $employee->user?->name ?? 'Sans compte' }}</strong>
        </div>
        <div class="card" style="padding:16px;">
            <div style="font-size:12px; color:#6b7280;">Congés annuels</div>
            <strong>{{ $employee->annual_leave_days ?? 18 }} j / an</strong>
        </div>
    </div>

    <section class="card app-table-card" style="margin-bottom:16px;">
        <div class="table-title" style="padding:12px 16px 0;">Informations RH</div>
        <div style="padding:16px; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; font-size:14px;">
            <div><span style="color:#6b7280;">Email</span><br>{{ $employee->email ?? '—' }}</div>
            <div><span style="color:#6b7280;">Téléphone</span><br>{{ $employee->phone ?? '—' }}</div>
            <div><span style="color:#6b7280;">Embauche</span><br>{{ $employee->hire_date?->format('d/m/Y') ?? '—' }}</div>
            <div><span style="color:#6b7280;">CNPS</span><br>{{ $employee->cnps_number ?? '—' }}</div>
            <div><span style="color:#6b7280;">Banque</span><br>{{ $employee->bank_name ?? '—' }}</div>
            <div><span style="color:#6b7280;">Compte</span><br>{{ $employee->bank_account ?? '—' }}</div>
        </div>
    </section>

    @if ($employee->salaryHistory->isNotEmpty())
        <section class="card app-table-card" style="margin-bottom:16px;">
            <div class="table-title" style="padding:12px 16px 0;">Historique salarial</div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Date effet</th><th>Montant</th><th>Précédent</th><th>Motif</th><th>Par</th></tr></thead>
                    <tbody>
                        @foreach ($employee->salaryHistory as $row)
                            <tr>
                                <td>{{ $row->effective_date->format('d/m/Y') }}</td>
                                <td><strong>{{ fmt_money($row->amount) }} FCFA</strong></td>
                                <td>{{ $row->previous_amount !== null ? fmt_money($row->previous_amount) . ' FCFA' : '—' }}</td>
                                <td>{{ $row->reason ?? '—' }}</td>
                                <td>{{ $row->changedBy?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($adjustmentsEnabled && $canAdjust)
        <section class="card" style="margin-bottom:16px; padding:20px;">
            <h3 class="card-title">Ajustements paie (période)</h3>
            <p style="font-size:13px; color:#6b7280; margin:0 0 16px;">
                Enregistrez ici les jours non payés, primes et retenues. Ils apparaîtront sur le bulletin après <strong>Recalculer</strong> sur la fiche de paie du même mois.
                La présence ne impacte plus la paie automatiquement.
            </p>
            <div class="form-grid" style="margin-bottom:16px;">
                <div class="field">
                    <label class="field-label">Début période</label>
                    <input class="input input-sm" type="date" wire:model.live="adj_period_start">
                </div>
                <div class="field">
                    <label class="field-label">Fin période</label>
                    <input class="input input-sm" type="date" wire:model.live="adj_period_end">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:20px;">
                <div style="border:1px solid #e5e7eb; border-radius:8px; padding:14px;">
                    <strong style="display:block; margin-bottom:8px;">Jours non payés</strong>
                    <input class="input input-sm" type="number" step="0.5" min="0.5" wire:model="unpaid_days" placeholder="Nb jours" style="margin-bottom:8px;">
                    <input class="input input-sm" wire:model="unpaid_reason" placeholder="Motif (ex. absences non justifiées)" style="margin-bottom:8px;">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addUnpaidDays">Enregistrer</button>
                </div>
                <div style="border:1px solid #e5e7eb; border-radius:8px; padding:14px;">
                    <strong style="display:block; margin-bottom:8px;">Prime / gain</strong>
                    <input class="input input-sm" type="number" step="1" min="1" wire:model="bonus_amount" placeholder="Montant FCFA" style="margin-bottom:8px;">
                    <input class="input input-sm" wire:model="bonus_reason" placeholder="Motif (ex. prime transport)" style="margin-bottom:8px;">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addBonus">Enregistrer</button>
                </div>
                <div style="border:1px solid #e5e7eb; border-radius:8px; padding:14px;">
                    <strong style="display:block; margin-bottom:8px;">Retenue</strong>
                    <input class="input input-sm" type="number" step="1" min="1" wire:model="deduction_amount" placeholder="Montant FCFA" style="margin-bottom:8px;">
                    <input class="input input-sm" wire:model="deduction_reason" placeholder="Motif (ex. avance sur salaire)" style="margin-bottom:8px;">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addDeduction">Enregistrer</button>
                </div>
            </div>

            @if ($periodAdjustments->isNotEmpty())
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr><th>Type</th><th>Détail</th><th>Motif</th><th>Enregistré par</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($periodAdjustments as $adj)
                                <tr>
                                    <td>{{ $adj->type_label }}</td>
                                    <td>
                                        @if ($adj->type === 'unpaid_days')
                                            {{ fmt_num($adj->days, 1) }} jour(s)
                                        @else
                                            {{ fmt_money($adj->amount) }} FCFA
                                        @endif
                                    </td>
                                    <td>{{ $adj->label }}</td>
                                    <td>{{ $adj->recordedBy?->name ?? '—' }}</td>
                                    <td>
                                        @if (!$adj->isLocked())
                                            <button type="button" class="btn btn-error btn-sm" wire:click="deleteAdjustment({{ $adj->id }})" onclick="return confirm('Supprimer cet ajustement ?')">Supprimer</button>
                                        @else
                                            <span style="font-size:11px; color:#9ca3af;">Paie traitée</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p style="color:#9ca3af; font-size:13px; margin:0;">Aucun ajustement sur cette période.</p>
            @endif
        </section>
    @elseif ($adjustmentsEnabled && !$canAdjust)
        <section class="card" style="margin-bottom:16px; padding:16px;">
            <p style="margin:0; color:#6b7280; font-size:13px;">Les ajustements paie (jours non payés, primes, retenues) nécessitent la permission <strong>Ajustements paie</strong>.</p>
        </section>
    @endif

    @if ($leaveEnabled && $employee->leaveBalances->isNotEmpty())
        <section class="card app-table-card" style="margin-bottom:16px;">
            <div class="table-title" style="padding:12px 16px 0;">Soldes congés ({{ now()->year }})</div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Type</th><th>Alloué</th><th>Utilisé</th><th>Restant</th></tr></thead>
                    <tbody>
                        @foreach ($employee->leaveBalances->where('year', now()->year) as $bal)
                            <tr>
                                <td>{{ $bal->leaveType?->name ?? '—' }}</td>
                                <td>{{ fmt_num($bal->allocated, 1) }} j</td>
                                <td>{{ fmt_num($bal->used, 1) }} j</td>
                                <td><strong>{{ fmt_num($bal->remaining, 1) }} j</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($leaveEnabled && $employee->leaveRequests->isNotEmpty())
        <section class="card app-table-card" style="margin-bottom:16px;">
            <div class="table-title" style="padding:12px 16px 0;">Demandes de congé récentes</div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Période</th><th>Type</th><th>Jours</th><th>Statut</th></tr></thead>
                    <tbody>
                        @foreach ($employee->leaveRequests->take(10) as $req)
                            <tr>
                                <td>{{ $req->start_date->format('d/m/Y') }} — {{ $req->end_date->format('d/m/Y') }}</td>
                                <td>{{ $req->leaveType?->name ?? '—' }}</td>
                                <td>{{ fmt_num($req->days, 1) }}</td>
                                <td><span class="badge badge-secondary">{{ $req->status_label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="card app-table-card">
        <div class="table-title" style="padding:12px 16px 0;">Historique paie</div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Période</th><th>Référence</th><th>Brut</th><th>Retenues</th><th>Net</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse ($payrollHistory as $line)
                        <tr>
                            <td>{{ $line->payrollRun?->period_start?->format('d/m/Y') }} — {{ $line->payrollRun?->period_end?->format('d/m/Y') }}</td>
                            <td>{{ $line->payrollRun?->reference ?? '—' }}</td>
                            <td>{{ fmt_money((float) $line->base_salary + (float) $line->bonuses) }} FCFA</td>
                            <td>{{ fmt_money($line->deductions) }} FCFA</td>
                            <td><strong>{{ fmt_money($line->net_salary) }} FCFA</strong></td>
                            <td>{{ $line->payrollRun?->status_label ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucune paie traitée pour cet employé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
