<div class="page-body">
    @include('pressing::livewire.settings.partials.nav')

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <section class="card" style="padding:16px;max-width:680px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div>
                <h2 style="margin-top:0;margin-bottom:4px;">{{ __('Programme de fidélité') }}</h2>
                <p style="color:#64748b;font-size:14px;margin:0;">
                    {{ __('Les points sont crédités quand une commande est entièrement payée.') }}
                </p>
            </div>
            <label class="field-toggle" style="white-space:nowrap;">
                <input type="checkbox" wire:model="active">
                {{ __('Activer') }}
            </label>
        </div>

        <hr style="margin:16px 0;border:0;border-top:1px solid #e5e7eb;">

        <h3 style="font-size:.95rem;margin:0 0 10px;">{{ __('Comment gagner des points') }}</h3>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">{{ __('Points par commande payée') }}</label>
                <input class="input" type="number" min="0" max="1000" wire:model="points_per_order">
                @error('points_per_order')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Montant (FCFA) pour 1 point bonus') }}</label>
                <input class="input" type="number" min="0" step="1" wire:model="amount_per_point" placeholder="0 = désactivé">
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">{{ __('0 = pas de points liés au montant') }}</div>
                @error('amount_per_point')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
            </div>
        </div>

        <hr style="margin:16px 0;border:0;border-top:1px solid #e5e7eb;">

        <h3 style="font-size:.95rem;margin:0 0 10px;">{{ __('Récompense') }}</h3>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">{{ __('Points nécessaires') }}</label>
                <input class="input" type="number" min="1" wire:model="threshold_points">
                @error('threshold_points')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Type de récompense') }}</label>
                <select class="input" wire:model.live="reward_type">
                    <option value="value">{{ __('Montant fixe (FCFA)') }}</option>
                    <option value="percent">{{ __('Pourcentage (%)') }}</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label">
                    {{ $reward_type === 'percent' ? __('Réduction (%)') : __('Réduction (FCFA)') }}
                </label>
                <input class="input" type="number" min="0" step="1" wire:model="reward_value">
                @error('reward_value')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
            </div>
            @if ($reward_type === 'percent')
                <div class="field">
                    <label class="field-label">{{ __('Plafond de réduction (FCFA)') }}</label>
                    <input class="input" type="number" min="0" step="1" wire:model="reward_max" placeholder="{{ __('optionnel') }}">
                    @error('reward_max')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
                </div>
            @endif
            <div class="field">
                <label class="field-label">{{ __('Validité du bon (jours)') }}</label>
                <input class="input" type="number" min="0" wire:model="reward_expiry_days" placeholder="0">
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">{{ __('0 = sans expiration') }}</div>
                @error('reward_expiry_days')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="margin-top:12px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;color:#475569;">
            {{ __('Exemple') }} :
            <strong>{{ $threshold_points ?: 10 }}</strong> {{ __('points') }} =
            <strong>
                @if ($reward_type === 'percent')
                    {{ $reward_value ?: 0 }}%
                @else
                    {{ number_format((float) ($reward_value ?: 0), 0, ',', ' ') }} FCFA
                @endif
            </strong>
            {{ __('de réduction sur une prochaine commande.') }}
        </div>

        @if ($canManage)
            <div style="margin-top:16px;">
                <button type="button" class="btn btn-primary" wire:click="save">{{ __('Enregistrer') }}</button>
            </div>
        @else
            <div class="alert alert-warning" style="margin-top:16px;">{{ __('Permission insuffisante pour modifier.') }}</div>
        @endif
    </section>
</div>
