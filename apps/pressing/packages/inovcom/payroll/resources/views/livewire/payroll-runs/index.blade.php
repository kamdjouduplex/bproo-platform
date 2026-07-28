@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
        <div class="card" style="padding:16px;">
            <div style="font-size:12px; color:#6b7280;">Employés actifs</div>
            <strong style="font-size:22px;">{{ $activeEmployees ?? 0 }}</strong>
        </div>
    </div>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Périodes de paie</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <select class="input input-sm" wire:model.live="statusFilter">
                    <option value="all">Tous</option>
                    <option value="draft">Brouillon</option>
                    <option value="processed">Traitée</option>
                    <option value="paid">Payée</option>
                </select>
                @if ($canCreate ?? false)
                    <a class="btn btn-primary" href="{{ route('tenant.payroll.create', ['tenant' => $tenantCode]) }}">Nouvelle fiche</a>
                @endif
                <a class="btn btn-secondary" href="{{ route('tenant.payroll.employees.index', ['tenant' => $tenantCode]) }}">Employés</a>
                @if (\Illuminate\Support\Facades\Route::has('tenant.payroll.leaves.index'))
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
                        <th>Brut</th>
                        <th>Retenues</th>
                        <th>Net</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td><strong>{{ $run->reference }}</strong></td>
                            <td>{{ $run->period_start->format('d/m/Y') }} — {{ $run->period_end->format('d/m/Y') }}</td>
                            <td>{{ fmt_money($run->total_gross) }} FCFA</td>
                            <td>{{ fmt_money($run->total_deductions) }} FCFA</td>
                            <td><strong>{{ fmt_money($run->total_net) }} FCFA</strong></td>
                            <td><span class="badge badge-secondary">{{ $run->status_label }}</span></td>
                            <td style="display:flex; gap:4px; flex-wrap:wrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.payroll.show', [$run->id, 'tenant' => $tenantCode]) }}">Ouvrir</a>
                                @if (($canProcess ?? false) && !$run->isPaid())
                                    <button type="button" class="btn btn-error btn-sm" wire:click="cancelRun({{ $run->id }})" onclick="return confirm('Annuler la fiche {{ $run->reference }} ?')">Annuler</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($runs->count() === 0)
                        <tr><td colspan="7">Aucune fiche de paie.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $runs->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>
</div>
