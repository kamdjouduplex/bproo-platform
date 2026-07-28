@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $cur = $tenantCurrency ?? config('inovcom.currency', 'XOF');
    $pv = (float) ($price ?? 0);
    $pa = (float) ($cost ?? 0);
    $margin = $pv > 0 ? round(($pv - $pa) / $pv * 100, 1) : 0;
    $marginColor = $margin >= 20 ? 'text-emerald-600' : ($margin >= 0 ? 'text-amber-600' : 'text-red-600');
@endphp

<div>

    {{-- ── Identification ──────────────────────────────────────────────── --}}
    <section class="card mb-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Identification') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Désignation') }} <span class="text-red-500">*</span></label>
                <input class="input" wire:model="name" placeholder="{{ __('Ex : Ciment CPA 42.5 — sac 50 kg') }}">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Référence / SKU') }}</label>
                <input class="input" wire:model="sku" placeholder="{{ __('Ex : CIM-CPA425-50') }}">
                @error('sku') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Catégorie') }}</label>
                <select class="input" wire:model="category_id">
                    <option value="">— {{ __('Aucune') }} —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2 mt-1.5">
                    <input class="input input-sm flex-1" wire:model="newCategoryName" placeholder="{{ __('Nouvelle catégorie…') }}">
                    <button class="btn btn-secondary btn-sm" type="button" wire:click="createCategory">{{ __('Créer') }}</button>
                </div>
                @error('category_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Unité de mesure') }}</label>
                <select class="input" wire:model="unit_id">
                    <option value="">— {{ __('Aucune') }} —</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">
                            {{ $unit->name }}{{ $unit->abbreviation ? ' (' . $unit->abbreviation . ')' : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="flex gap-2 mt-1.5">
                    <input class="input input-sm flex-1" wire:model="newUnitName" placeholder="{{ __('Nouvelle unité…') }}">
                    <input class="input input-sm w-20" wire:model="newUnitAbbr" placeholder="{{ __('Abrév.') }}">
                    <button class="btn btn-secondary btn-sm" type="button" wire:click="createUnit">{{ __('Créer') }}</button>
                </div>
                @error('unit_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Description') }}</label>
                <textarea class="input" wire:model="description" rows="3"
                    placeholder="{{ __("Caractéristiques, conditions d'utilisation, notes internes…") }}"></textarea>
                @error('description') <span class="field-error">{{ $message }}</span> @enderror
            </div>

        </div>
    </section>

    {{-- ── Tarification ─────────────────────────────────────────────────── --}}
    <section class="card mb-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Tarification') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div class="field">
                <label class="field-label">{{ __('Prix de vente unitaire') }} <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input class="input pr-14" wire:model="price" type="number" min="0" step="1" placeholder="0">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[12px] text-slate-400 font-semibold pointer-events-none">{{ $cur }}</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Prix affiché sur les devis et factures') }}</span>
                @error('price') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __("Coût d'achat") }}</label>
                <div class="relative">
                    <input class="input pr-14" wire:model="cost" type="number" min="0" step="1" placeholder="0">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[12px] text-slate-400 font-semibold pointer-events-none">{{ $cur }}</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Sert au calcul de la marge sur les devis') }}</span>
                @error('cost') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            @if($pv > 0)
            <div class="sm:col-span-2 flex items-center gap-4 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                <span class="text-[12px] text-slate-500">{{ __('Marge brute estimée') }}</span>
                <strong class="text-[15px] {{ $marginColor }}">{{ $margin }} %</strong>
                <span class="text-[12px] text-slate-400">
                    ({{ number_format($pv - $pa, 0, ',', ' ') }} {{ $cur }} {{ __('par unité') }})
                </span>
            </div>
            @endif

        </div>
    </section>

    {{-- ── Statut ───────────────────────────────────────────────────────── --}}
    <section class="card mb-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Statut') }}</h2>
        <label class="flex items-center gap-2.5 cursor-pointer text-[13px] text-slate-600">
            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-indigo-600 w-4 h-4 cursor-pointer">
            {{ __('Article actif (visible dans les sélecteurs de devis et factures)') }}
        </label>
    </section>

    {{-- ── Actions ──────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 mt-4">
        <a class="btn btn-secondary" href="{{ route('tenant.items.index', ['tenant' => $tenantCode]) }}">
            {{ __('Annuler') }}
        </a>
        <button class="btn btn-primary" type="button" wire:click="save" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">
                {{ $itemId ? __('Mettre à jour') : __('Enregistrer') }}
            </span>
            <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
        </button>
    </div>

</div>
