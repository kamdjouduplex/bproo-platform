<div class="page-body desktop-updates">
    <div class="card desktop-updates__card">
        <p class="desktop-updates__meta">
            Version installée :
            <strong>v{{ $currentVersion ?: '—' }}</strong>
            @if ($lastCheckAt)
                <span style="margin-left:0.5rem;font-size:0.85rem;">· Dernière vérif. {{ \Illuminate\Support\Str::of($lastCheckAt)->before('T') }}</span>
            @endif
        </p>

        @if ($message !== '')
            <div
                role="status"
                class="desktop-updates__status {{ $status === 'error' ? 'desktop-updates__status--error' : (($status === 'done' || $status === 'restart') ? 'desktop-updates__status--ok' : '') }}"
            >
                {{ $message }}
            </div>
        @endif

        @if ($updateAvailable && $availableVersion)
            <div class="desktop-updates__avail">
                <div class="desktop-updates__avail-title">Nouvelle version : v{{ $availableVersion }}</div>
                @if ($changelog)
                    <p style="margin:0.5rem 0 0;color:#334155;white-space:pre-wrap;">{{ $changelog }}</p>
                @endif
            </div>
        @endif

        <div class="desktop-updates__actions">
            <button
                type="button"
                class="btn btn-secondary"
                wire:click="checkForUpdates"
                wire:loading.attr="disabled"
                @disabled($status === 'checking' || $status === 'installing')
            >
                <span wire:loading.remove wire:target="checkForUpdates">Vérifier les mises à jour</span>
                <span wire:loading wire:target="checkForUpdates">Vérification…</span>
            </button>

            @if ($updateAvailable)
                <button
                    type="button"
                    class="btn btn-primary"
                    wire:click="installUpdate"
                    wire:loading.attr="disabled"
                    @disabled($status === 'checking' || $status === 'installing')
                >
                    <span wire:loading.remove wire:target="installUpdate">Installer v{{ $availableVersion }}</span>
                    <span wire:loading wire:target="installUpdate">Installation…</span>
                </button>
            @endif
        </div>

        @if ($restartRequired)
            <p class="desktop-updates__hint">
                Pour finaliser : fermez complètement Bproo Pharma (fenêtre Edge), puis double-cliquez à nouveau sur le raccourci bureau.
            </p>
        @endif

        <p class="desktop-updates__back">
            <a href="{{ route('tenant.dashboard', request()->query()) }}">← Retour au tableau de bord</a>
        </p>
    </div>
</div>
