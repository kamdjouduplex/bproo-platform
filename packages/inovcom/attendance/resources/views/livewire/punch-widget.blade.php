@if ($enabled ?? true)
    <div
        class="attendance-punch-widget"
        wire:poll.60s="refreshAttendanceStatus"
        x-data="{ flash: @entangle('punchFlashMessage') }"
        x-init="$watch('flash', v => { if (v) setTimeout(() => $wire.clearPunchFlash(), 3500) })"
    >
        @if ($punchFlashMessage)
            <span
                class="attendance-punch-widget__toast attendance-punch-widget__toast--{{ $punchFlashType }}"
                title="{{ $punchFlashMessage }}"
                role="status"
            >
                {{ $punchFlashType === 'success' ? '✓' : '!' }}
                <span class="attendance-punch-widget__toast-text">{{ $punchFlashMessage }}</span>
            </span>
        @endif

        <div class="attendance-punch-widget__chip {{ $isPresent ? 'is-present' : ($arrivalTime ? 'is-done' : 'is-idle') }}">
            <span class="attendance-punch-widget__dot" aria-hidden="true"></span>
            <span class="attendance-punch-widget__times" title="Aujourd’hui {{ $clock }}">
                @if ($arrivalTime || $departureTime)
                    <strong>{{ $arrivalTime ?? '—' }}</strong>
                    <span class="attendance-punch-widget__sep">→</span>
                    <strong>{{ $departureTime ?? '…' }}</strong>
                @else
                    <span class="attendance-punch-widget__idle-label">Pas encore pointé</span>
                @endif
            </span>

            @if ($canPunchIn)
                <button
                    type="button"
                    class="btn btn-sm btn-primary attendance-punch-btn"
                    wire:click="punchIn"
                    wire:loading.attr="disabled"
                    wire:target="punchIn,punchOut"
                    title="Enregistrer l’arrivée"
                >
                    <span wire:loading.remove wire:target="punchIn">Arrivée</span>
                    <span wire:loading wire:target="punchIn">…</span>
                </button>
            @elseif ($canPunchOut)
                <button
                    type="button"
                    class="btn btn-sm btn-primary attendance-punch-btn attendance-punch-btn--out"
                    wire:click="punchOut"
                    wire:loading.attr="disabled"
                    wire:target="punchIn,punchOut"
                    title="Arrivée {{ $arrivalTime }} — enregistrer le départ"
                >
                    <span wire:loading.remove wire:target="punchOut">Départ</span>
                    <span wire:loading wire:target="punchOut">…</span>
                </button>
            @endif

            @if (\Illuminate\Support\Facades\Route::has('tenant.attendance.index') && $tenantCode)
                <a
                    href="{{ route('tenant.attendance.index', ['tenant' => $tenantCode]) }}"
                    class="attendance-punch-widget__link"
                    title="Ouvrir la page Présence"
                >Présence</a>
            @endif
        </div>
    </div>
@else
    {{-- Livewire requires a single root HTML tag even when the widget is disabled. --}}
    <div class="attendance-punch-widget attendance-punch-widget--disabled" hidden aria-hidden="true"></div>
@endif
