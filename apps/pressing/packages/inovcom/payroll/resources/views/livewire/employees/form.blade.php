@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif

    <form wire:submit="save" class="card" style="padding: 24px; margin-bottom: 16px;">
        <h3 class="card-title" style="margin-bottom: 16px;">Identité</h3>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">N° employé</label>
                <input class="input" wire:model="employee_number" required>
                @error('employee_number') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label class="field-label">Prénom</label>
                <input class="input" wire:model="first_name" required>
                @error('first_name') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label class="field-label">Nom</label>
                <input class="input" wire:model="last_name" required>
                @error('last_name') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label class="field-label">Email</label>
                <input class="input" type="email" wire:model="email">
            </div>
            <div class="field">
                <label class="field-label">Téléphone</label>
                <input class="input" wire:model="phone">
            </div>
            <div class="field">
                <label class="field-label">Genre</label>
                <select class="input" wire:model="gender">
                    <option value="">—</option>
                    <option value="M">Masculin</option>
                    <option value="F">Féminin</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Date de naissance</label>
                <input class="input" type="date" wire:model="birth_date">
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label class="field-label">Adresse</label>
                <input class="input" wire:model="address">
            </div>
        </div>

        <h3 class="card-title" style="margin: 24px 0 16px;">Poste & contrat</h3>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Poste</label>
                <input class="input" wire:model="position">
            </div>
            <div class="field">
                <label class="field-label">Département</label>
                <input class="input" wire:model="department">
            </div>
            <div class="field">
                <label class="field-label">Type de contrat</label>
                <select class="input" wire:model="contract_type">
                    <option value="">—</option>
                    <option value="cdi">CDI</option>
                    <option value="cdd">CDD</option>
                    <option value="stage">Stage</option>
                    <option value="freelance">Freelance</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Date d'embauche</label>
                <input class="input" type="date" wire:model="hire_date">
            </div>
            <div class="field">
                <label class="field-label">Jours congé annuel</label>
                <input class="input" type="number" min="0" wire:model="annual_leave_days">
            </div>
            <div class="field">
                <label class="field-label">Compte utilisateur (optionnel)</label>
                <select class="input" wire:model="user_id">
                    <option value="">— Aucun compte —</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} @if($u->email)({{ $u->email }})@endif</option>
                    @endforeach
                </select>
                <p style="font-size:12px; color:#6b7280; margin-top:4px;">Un employé peut exister sans compte utilisateur (paie, congés, présence manuelle).</p>
            </div>
        </div>

        <h3 class="card-title" style="margin: 24px 0 16px;">Rémunération & banque</h3>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Salaire de base (FCFA)</label>
                <input class="input" type="number" step="0.01" min="0" wire:model="base_salary" required>
                @error('base_salary') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            @if ($employeeId)
                <div class="field">
                    <label class="field-label">Motif changement salaire</label>
                    <input class="input" wire:model="salary_change_reason" placeholder="Augmentation annuelle, promotion…">
                </div>
            @endif
            <div class="field">
                <label class="field-label">N° CNPS</label>
                <input class="input" wire:model="cnps_number">
            </div>
            <div class="field">
                <label class="field-label">Banque</label>
                <input class="input" wire:model="bank_name">
            </div>
            <div class="field">
                <label class="field-label">N° compte bancaire</label>
                <input class="input" wire:model="bank_account">
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label class="field-label">Notes internes</label>
                <textarea class="input" wire:model="notes" rows="2"></textarea>
            </div>
            <div class="field">
                <label class="field-toggle">
                    <input type="checkbox" wire:model="is_active"> Employé actif
                </label>
            </div>
        </div>

        <div class="page-actions" style="margin-top: 24px;">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            @if ($employeeId)
                <a class="btn btn-secondary" href="{{ route('tenant.payroll.employees.show', [$employeeId, 'tenant' => $tenantCode]) }}">← Fiche employé</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('tenant.payroll.employees.index', ['tenant' => $tenantCode]) }}">← Employés</a>
            <a class="btn btn-secondary" href="{{ route('tenant.payroll.index', ['tenant' => $tenantCode]) }}">Paie</a>
        </div>
    </form>
</div>
