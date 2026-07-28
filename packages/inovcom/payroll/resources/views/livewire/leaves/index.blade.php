@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp

<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <section class="card" style="margin-bottom:16px; padding:20px;">
        <h3 class="card-title">Nouvelle demande de congé</h3>
        <div class="form-grid" style="margin-top:12px;">
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
            </div>
            <div class="field">
                <label class="field-label">Au</label>
                <input class="input" type="date" wire:model="new_end_date">
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">Motif</label>
                <input class="input" wire:model="new_reason" placeholder="Optionnel">
            </div>
        </div>
        <button type="button" class="btn btn-primary" wire:click="createRequest" style="margin-top:12px;">Enregistrer la demande</button>
    </section>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Demandes de congé</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <select class="input input-sm" wire:model.live="statusFilter">
                    <option value="pending">En attente</option>
                    <option value="approved">Approuvées</option>
                    <option value="rejected">Refusées</option>
                    <option value="all">Toutes</option>
                </select>
                <select class="input input-sm" wire:model.live="employeeFilter">
                    <option value="">Tous employés</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                    @endforeach
                </select>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.payroll.employees.index', ['tenant' => $tenantCode]) }}">← Employés</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.payroll.index', ['tenant' => $tenantCode]) }}">Paie</a>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Employé</th><th>Type</th><th>Période</th><th>Jours</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($requests as $req)
                        <tr>
                            <td><strong>{{ $req->employee?->full_name ?? '—' }}</strong></td>
                            <td>{{ $req->leaveType?->name ?? '—' }}</td>
                            <td>{{ $req->start_date->format('d/m/Y') }} — {{ $req->end_date->format('d/m/Y') }}</td>
                            <td>{{ fmt_num($req->days, 1) }}</td>
                            <td><span class="badge badge-secondary">{{ $req->status_label }}</span></td>
                            <td style="display:flex; gap:4px;">
                                @if ($req->isPending())
                                    <button class="btn btn-success btn-sm" wire:click="approve({{ $req->id }})">Approuver</button>
                                    <button class="btn btn-error btn-sm" wire:click="reject({{ $req->id }})">Refuser</button>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucune demande.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($requests, 'links'))
            <div style="padding:12px;">{{ $requests->links() }}</div>
        @endif
    </section>
</div>
