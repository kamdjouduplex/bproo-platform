@php
    $tenantCode = $tenantCode ?? request()->query('tenant');
    $badgeFor = function (string $status): string {
        return match ($status) {
            'pending' => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-error',
            default => 'badge-secondary',
        };
    };
@endphp

<div class="page-body">
    <div class="page-actions" style="margin-bottom:16px; flex-wrap:wrap; gap:8px; align-items:center;">
        <a class="btn btn-secondary" href="{{ route('tenant.payroll.index', ['tenant' => $tenantCode]) }}">← Retour à la paie</a>
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
        <div class="card" style="padding:16px;">
            <div style="font-size:12px; color:#6b7280;">En attente</div>
            <strong style="font-size:22px;">{{ $pendingCount ?? 0 }}</strong>
        </div>
        <div class="card" style="padding:16px;">
            <div style="font-size:12px; color:#6b7280;">Approuvés ({{ $year ?? now()->year }})</div>
            <strong style="font-size:22px;">{{ $approvedYearCount ?? 0 }}</strong>
        </div>
    </div>

    @if (($balances ?? collect())->isNotEmpty())
        <section class="card app-table-card" style="margin-bottom:16px;">
            <div class="table-title" style="padding:12px 16px 0;">
                Soldes {{ $year ?? now()->year }}
                @php
                    $selectedEmp = ($employees ?? collect())->firstWhere('id', (int) $employeeFilter);
                @endphp
                @if ($selectedEmp)
                    — {{ $selectedEmp->full_name }}
                @endif
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Alloué</th>
                            <th>Utilisé</th>
                            <th>Restant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($balances as $bal)
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

    <section class="card" style="margin-bottom:16px; padding:20px;">
        <h3 class="card-title" style="margin:0 0 4px;">Nouvelle demande</h3>
        <p style="margin:0 0 14px; color:#6b7280; font-size:13px;">La demande sera en attente jusqu’à approbation.</p>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Employé</label>
                <select class="input" wire:model="new_employee_id">
                    <option value="">— Choisir —</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_number }})</option>
                    @endforeach
                </select>
                @error('new_employee_id') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label class="field-label">Type</label>
                <select class="input" wire:model="new_leave_type_id">
                    <option value="">— Choisir —</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }} @if(!$type->is_paid)(sans solde)@endif</option>
                    @endforeach
                </select>
                @error('new_leave_type_id') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label class="field-label">Du</label>
                <input class="input" type="date" wire:model="new_start_date">
                @error('new_start_date') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label class="field-label">Au</label>
                <input class="input" type="date" wire:model="new_end_date">
                @error('new_end_date') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">Motif <span style="font-weight:400;color:#94a3b8;">(optionnel)</span></label>
                <input class="input" wire:model="new_reason" placeholder="Ex. : voyage familial, rendez-vous médical…">
                @error('new_reason') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="button" class="btn btn-primary" wire:click="createRequest" wire:loading.attr="disabled">
                Enregistrer la demande
            </button>
        </div>
    </section>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Demandes</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <select class="input" wire:model.live="statusFilter" style="min-width:140px;">
                    <option value="pending">En attente</option>
                    <option value="approved">Approuvées</option>
                    <option value="rejected">Refusées</option>
                    <option value="all">Toutes</option>
                </select>
                <select class="input" wire:model.live="employeeFilter" style="min-width:180px;">
                    <option value="">Tous les employés</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Type</th>
                        <th>Période</th>
                        <th>Jours</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $req)
                        <tr>
                            <td>
                                <strong>{{ $req->employee?->full_name ?? '—' }}</strong>
                                @if ($req->employee?->employee_number)
                                    <div style="font-size:11px;color:#64748b;">{{ $req->employee->employee_number }}</div>
                                @endif
                            </td>
                            <td>{{ $req->leaveType?->name ?? '—' }}</td>
                            <td>{{ $req->start_date->format('d/m/Y') }} — {{ $req->end_date->format('d/m/Y') }}</td>
                            <td>{{ fmt_num($req->days, 1) }}</td>
                            <td>
                                <span class="badge {{ $badgeFor($req->status) }}">{{ $req->status_label }}</span>
                            </td>
                            <td>
                                @if ($req->isPending())
                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <button type="button" class="btn btn-success" wire:click="approve({{ $req->id }})" wire:loading.attr="disabled">Approuver</button>
                                        <button type="button" class="btn btn-error" wire:click="reject({{ $req->id }})" wire:loading.attr="disabled">Refuser</button>
                                    </div>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:28px; text-align:center; color:#64748b;">
                                Aucune demande
                                @if ($statusFilter === 'pending')
                                    en attente
                                @elseif ($statusFilter === 'approved')
                                    approuvée
                                @elseif ($statusFilter === 'rejected')
                                    refusée
                                @endif
                                .
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($requests, 'links'))
            <div style="padding:12px;">{{ $requests->links() }}</div>
        @endif
    </section>
</div>
