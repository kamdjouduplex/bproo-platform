@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    @if ($canViewAll ?? false)
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
            <div class="card" style="padding:16px;">
                <div style="font-size:12px; color:#6b7280;">Employés actifs</div>
                <strong style="font-size:22px;">{{ $activeEmployees ?? 0 }}</strong>
            </div>
        </div>
    @endif

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">{{ ($canViewAll ?? false) ? 'Périodes de paie' : 'Mes périodes' }}</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <select class="input" wire:model.live="statusFilter" style="min-width:140px;">
                    <option value="all">Tous</option>
                    <option value="draft">Brouillon</option>
                    <option value="processed">Traitée</option>
                    <option value="paid">Payée</option>
                </select>
                @if ($canCreate ?? false)
                    <a class="btn btn-primary" href="{{ route('tenant.payroll.create', ['tenant' => $tenantCode]) }}">Nouvelle fiche</a>
                @endif
                @if ($canViewAll ?? false)
                    <a class="btn btn-secondary" href="{{ route('tenant.users.index', ['tenant' => $tenantCode]) }}">Utilisateurs</a>
                @endif
                @if (($canLeave ?? false) && \Illuminate\Support\Facades\Route::has('tenant.payroll.leaves.index'))
                    <a class="btn btn-secondary" href="{{ route('tenant.payroll.leaves.index', ['tenant' => $tenantCode]) }}">Congés</a>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Période</th>
                        @if ($canViewAll ?? false)
                            <th>Employés</th>
                            <th>Brut</th>
                            <th>Retenues</th>
                            <th>Net</th>
                        @else
                            <th>Mon net</th>
                        @endif
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        @php
                            $employeeNames = $run->lines
                                ->map(fn ($line) => $line->employee?->full_name)
                                ->filter()
                                ->values();
                            $employeeCount = $employeeNames->count();
                            $previewNames = $employeeNames->take(3);
                            $moreCount = max(0, $employeeCount - $previewNames->count());
                            $myLine = (!($canViewAll ?? false) && ($ownEmployeeId ?? null))
                                ? $run->lines->firstWhere('employee_id', $ownEmployeeId)
                                : null;
                        @endphp
                        <tr>
                            <td><strong>{{ $run->reference }}</strong></td>
                            <td>{{ $run->period_start->format('d/m/Y') }} — {{ $run->period_end->format('d/m/Y') }}</td>
                            @if ($canViewAll ?? false)
                                <td style="min-width:180px; max-width:280px;">
                                    @if ($employeeCount === 0)
                                        <span style="color:#94a3b8;">Aucun employé</span>
                                    @else
                                        <div style="font-size:12px; line-height:1.45;">
                                            <strong>{{ $employeeCount }}</strong>
                                            {{ $employeeCount > 1 ? 'employés' : 'employé' }}
                                            <div style="color:#475569; margin-top:2px;">
                                                {{ $previewNames->implode(', ') }}
                                                @if ($moreCount > 0)
                                                    <span style="color:#94a3b8;">+{{ $moreCount }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td>{{ fmt_money($run->total_gross) }} FCFA</td>
                                <td>{{ fmt_money($run->total_deductions) }} FCFA</td>
                                <td><strong>{{ fmt_money($run->total_net) }} FCFA</strong></td>
                            @else
                                <td><strong>{{ $myLine ? fmt_money($myLine->net_salary).' FCFA' : '—' }}</strong></td>
                            @endif
                            <td>
                                <span class="badge {{ $run->isLocked() ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $run->status_label }}
                                </span>
                            </td>
                            <td style="display:flex; gap:4px; flex-wrap:wrap;">
                                <a class="btn btn-secondary" href="{{ route('tenant.payroll.show', [$run->id, 'tenant' => $tenantCode]) }}">
                                    {{ ($canViewAll ?? false) ? ($run->isLocked() ? 'Consulter' : 'Ouvrir') : 'Mon bulletin' }}
                                </a>
                                @if (($canProcess ?? false) && !$run->isLocked())
                                    <button type="button" class="btn btn-error" wire:click="cancelRun({{ $run->id }})" onclick="return confirm('Annuler la fiche {{ $run->reference }} ?')">Annuler</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($runs->count() === 0)
                        <tr>
                            <td colspan="{{ ($canViewAll ?? false) ? 8 : 5 }}">
                                {{ ($canViewAll ?? false) ? 'Aucune fiche de paie.' : 'Aucun bulletin pour votre compte.' }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $runs->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>
</div>
