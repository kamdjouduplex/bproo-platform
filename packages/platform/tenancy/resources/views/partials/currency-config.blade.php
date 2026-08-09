{{-- Shared currency multi-select — wire:model (no FX rates) --}}
@php
    $catalog = $currencyCatalog ?? collect();
    $labelClass = $labelClass ?? 'label';
@endphp
<div class="field" style="grid-column: 1 / -1;" wire:key="currency-config">
    <label class="{{ $labelClass }}">Devises du point de vente</label>
    <p style="margin: 6px 0 14px; font-size: 13px; color: #64748b; line-height: 1.45; max-width: 52rem;">
        Activez une ou plusieurs devises (ex. <strong>CDF</strong> et <strong>USD</strong>).
        Chaque devise est comptabilisée <strong>séparément</strong> — aucun taux de change, aucun mélange de montants.
        La devise par défaut sert uniquement aux prix catalogue et aux documents sans devise explicite.
    </p>

    @if ($catalog->isEmpty())
        <p style="color: #b45309; font-size: 13px; margin: 0;">
            Catalogue indisponible. Migrer le Control Center (<code>platform_currencies</code>), puis réessayez.
        </p>
    @else
        <div style="display: grid; gap: 10px; max-width: 40rem;">
            @foreach ($catalog as $cur)
                @php
                    $code = $cur->code;
                    $enabled = in_array($code, $enabled_currency_codes ?? [], true);
                @endphp
                <div
                    wire:key="cur-row-{{ $code }}"
                    style="display: flex; flex-wrap: wrap; align-items: center; gap: 14px; padding: 12px 14px; border: 1px solid {{ $enabled ? '#93c5fd' : '#e2e8f0' }}; border-radius: 10px; background: {{ $enabled ? '#eff6ff' : '#fff' }};"
                >
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1 1 220px; min-width: 0;">
                        <input
                            type="checkbox"
                            value="{{ $code }}"
                            wire:model.live="enabled_currency_codes"
                            style="width: 18px; height: 18px; accent-color: #2563eb;"
                        >
                        <span>
                            <strong style="letter-spacing: 0.02em;">{{ $code }}</strong>
                            <span style="color: #475569;"> — {{ $cur->name }}@if($cur->symbol) ({{ $cur->symbol }})@endif</span>
                        </span>
                    </label>

                    @if ($enabled)
                        <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; white-space: nowrap;">
                            <input
                                type="radio"
                                value="{{ $code }}"
                                wire:model.live="default_currency_code"
                                style="accent-color: #2563eb;"
                            >
                            <span>Devise par défaut</span>
                        </label>
                    @endif
                </div>
            @endforeach
        </div>

        @if (count($enabled_currency_codes ?? []) > 0)
            <p style="margin: 12px 0 0; font-size: 12px; color: #64748b;">
                Activées :
                @foreach ($enabled_currency_codes as $c)
                    <span style="display: inline-block; margin: 2px 4px 0 0; padding: 2px 8px; border-radius: 999px; background: #e2e8f0; font-weight: 600;">{{ $c }}@if($c === $default_currency_code) ★@endif</span>
                @endforeach
            </p>
        @endif
    @endif

    @error('enabled_currency_codes') <span class="text-error" style="display:block;margin-top:8px;">{{ $message }}</span> @enderror
    @error('default_currency_code') <span class="text-error" style="display:block;margin-top:8px;">{{ $message }}</span> @enderror
</div>
