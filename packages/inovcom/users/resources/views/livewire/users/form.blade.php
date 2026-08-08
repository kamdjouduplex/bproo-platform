@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div class="page-body">
    <section class="card" style="padding:24px; margin-bottom:16px;">
        <h2 class="card-title">{{ $userId ? 'Modifier utilisateur' : 'Nouvel utilisateur' }}</h2>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Nom complet</label>
                <input class="input" wire:model="name" placeholder="Ex: Jean Dupont">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Email</label>
                <input class="input" wire:model="email" type="email" placeholder="jean@example.com">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            @if (!empty($hasPhoneColumn))
                <div class="field">
                    <label class="field-label">Téléphone (connexion + kiosk)</label>
                    <input class="input" wire:model="phone" type="tel" placeholder="670274538">
                    @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            @endif
            <div class="field">
                <label class="field-label">Mot de passe {{ $userId ? '(vide = ne pas modifier)' : '' }}</label>
                <input class="input" wire:model="password" type="password" placeholder="Min. 8 caracteres" autocomplete="new-password">
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Confirmer mot de passe</label>
                <input class="input" wire:model="password_confirmation" type="password" autocomplete="new-password">
                @error('password_confirmation') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Rôles</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    @foreach ($roles as $role)
                        <label class="field-toggle">
                            <input type="checkbox" wire:model="role_ids" value="{{ $role->id }}">
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('role_ids.*') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            @if(isset($stores) && count($stores) > 0)
                <div class="field">
                    <label class="field-label">Boutique assignée</label>
                    <select class="input" wire:model="assigned_store_id">
                        <option value="">Choisir une boutique</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_store_id') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            @endif
            @if(!empty($canAssignAgence) && isset($agences) && count($agences) > 0)
                <div class="field">
                    <label class="field-label">Agence de travail</label>
                    <select class="input" wire:model="assigned_agence_id">
                        <option value="">Choisir une agence</option>
                        @foreach($agences as $agence)
                            <option value="{{ $agence->id }}">{{ $agence->name }} ({{ $agence->code }})</option>
                        @endforeach
                    </select>
                    @error('assigned_agence_id') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            @endif
            <label class="field-toggle">
                <input type="checkbox" wire:model="is_active"> Compte actif
            </label>
        </div>
    </section>

    @if (!empty($payrollEnabled))
        <section class="card" style="padding:24px; margin-bottom:16px;">
            <h2 class="card-title" style="margin-bottom:8px;">Fiche employé (paie & présence)</h2>
            <p style="color:#64748b; font-size:0.9rem; margin-bottom:16px;">
                Gérée ici uniquement — plus de création séparée dans Paie.
                @if ($employee_number !== '')
                    Matricule : <strong>{{ $employee_number }}</strong>
                @else
                    Un matricule sera attribué automatiquement à l’enregistrement.
                @endif
            </p>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Code de pointage (4–6 chiffres, vide = généré)</label>
                    <input class="input" wire:model="punch_pin" type="text" inputmode="numeric" placeholder="Ex: 4321" autocomplete="off">
                    @error('punch_pin') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Poste</label>
                    <input class="input" wire:model="position">
                </div>
                <div class="field">
                    <label class="field-label">Département</label>
                    <input class="input" wire:model="department">
                </div>
                <div class="field">
                    <label class="field-label">Contrat</label>
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
                    <label class="field-label">Date d’embauche</label>
                    <input class="input" type="date" wire:model="hire_date">
                </div>
                <div class="field">
                    <label class="field-label">Salaire de base (FCFA)</label>
                    <input class="input" type="number" step="1" min="0" wire:model="base_salary">
                    @error('base_salary') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                @if ($userId)
                    <div class="field">
                        <label class="field-label">Motif changement salaire</label>
                        <input class="input" wire:model="salary_change_reason" placeholder="Augmentation, promotion…">
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
                    <label class="field-label">N° compte</label>
                    <input class="input" wire:model="bank_account">
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
                <div class="field">
                    <label class="field-label">Congés annuels (jours)</label>
                    <input class="input" type="number" min="0" wire:model="annual_leave_days">
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Adresse</label>
                    <input class="input" wire:model="address">
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Notes RH</label>
                    <textarea class="input" rows="2" wire:model="hr_notes"></textarea>
                </div>
            </div>
        </section>
    @endif

    <div class="page-actions">
        <a class="btn btn-secondary" href="{{ route('tenant.users.index', ['tenant' => $tenantCode]) }}">Retour</a>
        <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $userId ? 'Mettre à jour' : 'Enregistrer' }}</span>
            <span wire:loading wire:target="save">Enregistrement…</span>
        </button>
    </div>
</div>
