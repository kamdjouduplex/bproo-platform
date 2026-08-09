{{-- Shared currency multi-select for Configuration + Control Center --}}
@php
    $catalog = $currencyCatalog ?? collect();
@endphp
<div class="field" style="grid-column: 1 / -1;">
    <label class="{{ $labelClass ?? 'label' }}">Devises du point de vente</label>
    <p style="margin: 4px 0 10px; font-size: 13px; color: #64748b;">
        Cochez une ou plusieurs devises (ex. CDF + USD). La devise par défaut sert pour les prix catalogue et la compta locale.
        Pour une devise secondaire, indiquez combien d’unités de la devise par défaut vaut <strong>1</strong> unité de cette devise
        (ex. 1 USD = 2850 CDF si CDF est la devise par défaut).
    </p>
    <div style="display: grid; gap: 10px;">
        @forelse ($catalog as $cur)
            @php
                $code = $cur->code;
                $enabled = in_array($code, $enabled_currency_codes, true);
                $isDefault = $default_currency_code === $code;
            @endphp
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: {{ $enabled ? '#f8fafc' : '#fff' }};">
                <label style="display: flex; align-items: center; gap: 8px; min-width: 220px; cursor: pointer;">
                    <input type="checkbox"
                           @checked($enabled)
                           wire:click.prevent="toggleCurrencyCode('{{ $code }}')">
                    <span><strong>{{ $code }}</strong> — {{ $cur->name }}@if($cur->symbol) ({{ $cur->symbol }})@endif</span>
                </label>
                @if ($enabled)
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                        <input type="radio"
                               name="default_currency_{{ $wireKey ?? 'tenant' }}"
                               @checked($isDefault)
                               wire:click.prevent="setDefaultCurrencyCode('{{ $code }}')">
                        Par défaut
                    </label>
                    @if (! $isDefault)
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                            Taux → {{ $default_currency_code }}
                            <input class="input input-sm"
                                   type="number"
                                   min="0.000001"
                                   step="any"
                                   style="width: 140px;"
                                   wire:model.live="currency_rates.{{ $code }}"
                                   title="1 {{ $code }} = ? {{ $default_currency_code }}">
                        </label>
                    @else
                        <span style="font-size: 12px; color: #64748b;">Taux = 1 (référence)</span>
                    @endif
                @endif
            </div>
        @empty
            <p style="color: #b45309; font-size: 13px;">
                Catalogue des devises indisponible. Migrer le Control Center (tables <code>platform_currencies</code>), puis réessayez.
            </p>
        @endforelse
    </div>
    @error('enabled_currency_codes') <span class="text-error">{{ $message }}</span> @enderror
    @error('default_currency_code') <span class="text-error">{{ $message }}</span> @enderror
</div>
