@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    @if ($run)
        <section class="card" style="margin-bottom:16px; padding:16px;">
            <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:center; justify-content:space-between;">
                <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:center;">
                    <div>
                        <div style="font-size:12px; color:#6b7280;">Référence</div>
                        <strong>{{ $run->reference }}</strong>
                        <span class="badge {{ $run->isLocked() ? 'badge-success' : 'badge-secondary' }}" style="margin-left:8px;">
                            {{ $run->status_label }}
                        </span>
                    </div>
                    <div>
                        <div style="font-size:12px; color:#6b7280;">{{ ($canViewAll ?? true) ? 'Total net' : 'Mon net' }}</div>
                        <strong style="font-size:20px; color:#16a34a;">
                            {{ fmt_money(($canViewAll ?? true) ? $run->total_net : ($myNet ?? 0)) }} FCFA
                        </strong>
                    </div>
                </div>
                <a class="btn btn-secondary" href="{{ route('tenant.payroll.index', ['tenant' => $tenantCode]) }}">← Retour</a>
            </div>
        </section>
    @endif

    @if ($canViewAll ?? true)
        <form wire:submit="save" class="card" style="padding:24px; margin-bottom:16px;">
            <div class="form-grid" style="max-width:720px;">
                <div class="field">
                    <label class="field-label">Début période</label>
                    <input class="input" type="date" wire:model="period_start" @if($run && !$run->isDraft()) disabled @endif required>
                </div>
                <div class="field">
                    <label class="field-label">Fin période</label>
                    <input class="input" type="date" wire:model="period_end" @if($run && !$run->isDraft()) disabled @endif required>
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Notes</label>
                    <input class="input" wire:model="notes" @if($run && !$run->isDraft()) disabled @endif>
                </div>
            </div>
            @if (!$run || $run->isDraft())
                <p style="font-size:13px; color:#6b7280; margin:12px 0 0;">
                    Les primes, retenues et jours non payés se saisissent sur la <strong>fiche employé</strong> (section Ajustements paie), puis cliquez <strong>Recalculer</strong> ici.
                </p>
            @endif
            <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
                @if (!$run || $run->isDraft())
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    @if ($canUpdate ?? false)
                        <button type="button" class="btn btn-secondary" wire:click="recalculate">Recalculer</button>
                    @endif
                @endif
                @if ($run && $run->isDraft() && ($canProcess ?? false))
                    <button type="button" class="btn btn-warning" wire:click="process" onclick="return confirm('Traiter cette fiche ?')">Traiter</button>
                @endif
                @if ($run && $run->isProcessed() && ($canProcess ?? false))
                    <button
                        type="button"
                        class="btn btn-success"
                        wire:click="markAsPaid"
                        onclick="return confirm('Marquer cette fiche comme payée ?\n\nLes bulletins seront verrouillés définitivement (plus de modification ni d’annulation).')"
                    >Marquer payée (verrouiller)</button>
                @endif
                @if ($run && !$run->isLocked() && ($canProcess ?? false))
                    <button type="button" class="btn btn-error" wire:click="cancel" onclick="return confirm('Annuler définitivement cette fiche de paie ? Les ajustements employés resteront disponibles pour une nouvelle fiche.')">Annuler la fiche</button>
                @endif
                <a class="btn btn-secondary" href="{{ route('tenant.payroll.index', ['tenant' => $tenantCode]) }}">← Retour</a>
            </div>
        </form>
    @else
        <section class="card" style="padding:16px; margin-bottom:16px;">
            <p style="margin:0; color:#64748b; font-size:14px;">
                Période : <strong>{{ $run?->period_start?->format('d/m/Y') }} — {{ $run?->period_end?->format('d/m/Y') }}</strong>
                @if ($run?->notes)
                    · {{ $run->notes }}
                @endif
            </p>
        </section>
    @endif

    @if (count($lines) > 0)
        <section class="card app-table-card">
            <div class="table-title" style="padding:12px 16px 0;">{{ ($canViewAll ?? true) ? 'Bulletins par employé' : 'Mon bulletin' }}</div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Base</th>
                            <th>Primes</th>
                            <th>Retenues</th>
                            <th>Net</th>
                            <th>Détail bulletin</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $empId => $line)
                            @php $dbLine = $run?->lines?->firstWhere('employee_id', $empId); @endphp
                            <tr>
                                <td><strong>{{ $employeeNames[$empId] ?? '#' . $empId }}</strong></td>
                                <td>{{ fmt_money((float) ($line['base_salary'] ?? 0)) }}</td>
                                <td>{{ fmt_money((float) ($line['bonuses'] ?? 0)) }}</td>
                                <td>{{ fmt_money((float) ($line['deductions'] ?? 0)) }}</td>
                                <td><strong>{{ fmt_money((float) ($line['net_salary'] ?? 0)) }} FCFA</strong></td>
                                <td>
                                    @if ($dbLine && $dbLine->items->isNotEmpty())
                                        <ul style="margin:0; padding-left:16px; font-size:12px;">
                                            @foreach ($dbLine->items as $item)
                                                <li>{{ $item->label }} : {{ fmt_money($item->amount) }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="display:flex; gap:4px; flex-wrap:wrap; align-items:center;">
                                    @if (($canViewAll ?? true) && ! $run?->isLocked())
                                        <a class="btn btn-secondary" href="{{ route('tenant.payroll.employees.show', [$empId, 'tenant' => $tenantCode]) }}">Ajustements</a>
                                    @endif
                                    @if ($dbLine && $run)
                                        <a
                                            class="btn btn-primary"
                                            target="_blank"
                                            rel="noopener"
                                            title="Imprimer le bulletin de paie"
                                            href="{{ route('tenant.payroll.payslip.print', ['payroll_run' => $run->id, 'line' => $dbLine->id, 'tenant' => $tenantCode]) }}"
                                            style="display:inline-flex; align-items:center; gap:6px;"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                                <rect x="6" y="14" width="12" height="8"></rect>
                                            </svg>
                                            Imprimer bulletin
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($run)
        <p class="card" style="padding:24px;">{{ ($canViewAll ?? true) ? 'Aucun employé actif.' : 'Aucun bulletin trouvé pour votre compte sur cette période.' }}</p>
    @endif
</div>
