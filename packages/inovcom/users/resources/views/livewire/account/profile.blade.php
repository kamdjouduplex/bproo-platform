@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp
<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px;">
        <section class="card" style="padding:20px;">
            <div class="card-title" style="margin-bottom:12px;">Identité</div>
            @if ($hasEmployee)
                <p style="margin:0 0 12px; font-size:0.95rem;">
                    Matricule : <strong>{{ $matricule }}</strong>
                    @if ($position) · {{ $position }} @endif
                </p>
            @endif

            <div class="form-group" style="margin-bottom:12px;">
                <label class="label">Nom</label>
                <input class="input" type="text" wire:model="name">
                @error('name') <div class="text-danger" style="margin-top:4px;">{{ $message }}</div> @enderror
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="label">Email</label>
                <input class="input" type="email" wire:model="email">
                @error('email') <div class="text-danger" style="margin-top:4px;">{{ $message }}</div> @enderror
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="label">Téléphone (connexion + pointage kiosk)</label>
                <input class="input" type="tel" wire:model="phone" placeholder="670274538">
                @error('phone') <div class="text-danger" style="margin-top:4px;">{{ $message }}</div> @enderror
            </div>
            @if(\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'preferred_locale'))
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="label">Langue préférée</label>
                    <select class="input" wire:model="preferred_locale">
                        @foreach((class_exists(\School\Support\SchoolLocaleCatalog::class)
                            ? \School\Support\SchoolLocaleCatalog::enabled(app()->bound('tenant') ? app('tenant') : null)
                            : ['fr' => 'Français', 'en' => 'English']) as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('preferred_locale') <div class="text-danger" style="margin-top:4px;">{{ $message }}</div> @enderror
                </div>
            @endif
            <button type="button" class="btn btn-primary" wire:click="saveProfile" wire:loading.attr="disabled">
                Enregistrer le profil
            </button>
        </section>

        @if ($hasAttendance && ($canPunch ?? false))
            <section class="card" style="padding:20px;">
                <div class="card-title" style="margin-bottom:12px;">Présence aujourd’hui</div>
                <p style="margin:0 0 12px;">
                    Arrivée <strong>{{ $arrivalTime ?? '—' }}</strong>
                    → Départ <strong>{{ $departureTime ?? '—' }}</strong>
                </p>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    @if ($canPunchIn)
                        <button type="button" class="btn btn-primary" wire:click="punchIn" wire:loading.attr="disabled">Pointer l’arrivée</button>
                    @elseif ($canPunchOut)
                        <button type="button" class="btn btn-primary" wire:click="punchOut" wire:loading.attr="disabled">Pointer le départ</button>
                    @else
                        <span class="badge badge-success">Journée clôturée</span>
                    @endif
                    @if (\Illuminate\Support\Facades\Route::has('tenant.attendance.index'))
                        <a class="btn btn-secondary" href="{{ route('tenant.attendance.index', ['tenant' => $tenantCode]) }}">Mon historique</a>
                    @endif
                </div>
            </section>
        @endif

        <section class="card" style="padding:20px;">
            <div class="card-title" style="margin-bottom:12px;">Mot de passe</div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="label">Mot de passe actuel</label>
                <input class="input" type="password" wire:model="current_password" autocomplete="current-password">
                @error('current_password') <div class="text-danger" style="margin-top:4px;">{{ $message }}</div> @enderror
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="label">Nouveau mot de passe</label>
                <input class="input" type="password" wire:model="password" autocomplete="new-password">
                @error('password') <div class="text-danger" style="margin-top:4px;">{{ $message }}</div> @enderror
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label class="label">Confirmation</label>
                <input class="input" type="password" wire:model="password_confirmation" autocomplete="new-password">
            </div>
            <button type="button" class="btn btn-secondary" wire:click="changePassword" wire:loading.attr="disabled">
                Changer le mot de passe
            </button>
        </section>

        @if ($hasEmployee)
            <section class="card" style="padding:20px;">
                <div class="card-title" style="margin-bottom:8px;">Code de pointage (kiosk)</div>
                <p style="color:#64748b; font-size:0.9rem; margin-bottom:12px; line-height:1.45;">
                    Sur le lien public de pointage, utilisez votre <strong>téléphone</strong> + ce <strong>code personnel</strong> (4 à 6 chiffres).
                </p>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="label">Nouveau code</label>
                    <input class="input" type="password" inputmode="numeric" wire:model="punch_pin" autocomplete="off">
                    @error('punch_pin') <div class="text-danger" style="margin-top:4px;">{{ $message }}</div> @enderror
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="label">Confirmation du code</label>
                    <input class="input" type="password" inputmode="numeric" wire:model="punch_pin_confirmation" autocomplete="off">
                </div>
                <button type="button" class="btn btn-secondary" wire:click="changePunchPin" wire:loading.attr="disabled">
                    Mettre à jour le code
                </button>
            </section>
        @endif
    </div>
</div>
