<div class="att-kiosk-wrap">
    <div class="att-kiosk-card">
        <div class="att-kiosk-brand">{{ $tenantName }}</div>
        <h1 class="att-kiosk-title">Pointage</h1>

        @if (! $networkConfigured)
            <div class="att-kiosk-alert att-kiosk-alert--warn">
                Le réseau d’entreprise n’est pas encore configuré par l’administrateur. Le contrôle Wi‑Fi n’est pas actif.
            </div>
        @elseif (! $networkOk)
            <div class="att-kiosk-alert att-kiosk-alert--error" role="alert">
                {{ $networkMessage }}
                @if ($clientIp)
                    <div style="margin-top:8px;font-size:0.8rem;opacity:.85;">IP détectée : {{ $clientIp }}</div>
                @endif
            </div>
        @endif

        @if ($flashMessage)
            <div class="att-kiosk-alert att-kiosk-alert--{{ $flashType === 'success' ? 'success' : 'error' }}" role="status">
                {{ $flashMessage }}
            </div>
        @endif

        @error('phone')
            <div class="att-kiosk-alert att-kiosk-alert--error">{{ $message }}</div>
        @enderror
        @error('code')
            <div class="att-kiosk-alert att-kiosk-alert--error">{{ $message }}</div>
        @enderror

        @if (! $identified)
            <form wire:submit="identify">
                <div class="att-kiosk-field">
                    <label for="att-phone">Numéro de téléphone</label>
                    <input id="att-phone" type="tel" wire:model="phone" autocomplete="tel" placeholder="Ex. 6XX XX XX XX" inputmode="tel">
                </div>
                <div class="att-kiosk-field">
                    <label for="att-code">Code de pointage personnel</label>
                    <input id="att-code" type="password" wire:model="code" autocomplete="one-time-code" inputmode="numeric" placeholder="4 à 6 chiffres">
                </div>
                <div class="att-kiosk-actions">
                    <button type="submit" class="btn btn-primary" @disabled(! $networkOk && $networkConfigured) wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="identify">Continuer</span>
                        <span wire:loading wire:target="identify">Vérification…</span>
                    </button>
                </div>
            </form>
        @else
            <p style="margin:0 0 12px;font-weight:600;">Bonjour, {{ $employeeName }}</p>
            <div class="att-kiosk-status">
                <div>
                    <span>Arrivée</span>
                    <strong>{{ $arrivalTime ?? '—' }}</strong>
                </div>
                <div style="text-align:right;">
                    <span>Départ</span>
                    <strong>{{ $departureTime ?? '—' }}</strong>
                </div>
            </div>
            <div class="att-kiosk-actions">
                @if ($canPunchIn)
                    <button
                        type="button"
                        class="btn btn-primary"
                        wire:click="punchIn"
                        wire:loading.attr="disabled"
                        @disabled(! $networkOk && $networkConfigured)
                    >
                        <span wire:loading.remove wire:target="punchIn">Pointer l’arrivée</span>
                        <span wire:loading wire:target="punchIn">Enregistrement…</span>
                    </button>
                @elseif ($canPunchOut)
                    <button
                        type="button"
                        class="btn btn-primary"
                        wire:click="punchOut"
                        wire:loading.attr="disabled"
                        @disabled(! $networkOk && $networkConfigured)
                    >
                        <span wire:loading.remove wire:target="punchOut">Pointer le départ</span>
                        <span wire:loading wire:target="punchOut">Enregistrement…</span>
                    </button>
                @else
                    <div class="att-kiosk-alert att-kiosk-alert--success">Journée déjà clôturée.</div>
                @endif
                <button type="button" class="btn btn-secondary" wire:click="resetSession">Changer d’employé</button>
            </div>
        @endif
    </div>
</div>
