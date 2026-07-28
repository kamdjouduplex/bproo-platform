@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div>
    <div class="card">
        <h2 class="text-base font-semibold text-slate-800 mb-5">{{ $supplierId ? __('Modifier le fournisseur') : __('Nouveau fournisseur') }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Code') }}</label>
                @if($supplierId)
                    <input class="input bg-slate-50 text-slate-500" wire:model="code" readonly disabled>
                    @error('code') <span class="field-error">{{ $message }}</span> @enderror
                @else
                    <input class="input bg-slate-50 text-slate-400" value="—" readonly disabled>
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Généré automatiquement (ex. FOU00001)') }}</span>
                @endif
            </div>

            <div class="field">
                <label class="field-label">{{ __('Nom du fournisseur') }} <span class="text-red-500">*</span></label>
                <input class="input" wire:model="name" placeholder="{{ __('Raison sociale') }}">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Nom du contact') }}</label>
                <input class="input" wire:model="contact_name" placeholder="{{ __('Prénom Nom') }}">
                @error('contact_name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Email') }}</label>
                <input class="input" type="email" wire:model="email" placeholder="contact@fournisseur.com">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Téléphone') }}</label>
                <input class="input" wire:model="phone" placeholder="+225 XX XX XX XX">
                @error('phone') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field flex items-center gap-2 pt-6">
                <input type="checkbox" wire:model="is_active" id="is_active" class="rounded border-slate-300 text-blue-600 w-4 h-4">
                <label for="is_active" class="text-[13px] text-slate-700 cursor-pointer">{{ __('Fournisseur actif') }}</label>
            </div>

            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Adresse') }}</label>
                <textarea class="input" wire:model="address" rows="2" placeholder="{{ __('Adresse complète') }}"></textarea>
            </div>

            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Notes') }}</label>
                <textarea class="input" wire:model="notes" rows="2" placeholder="{{ __('Conditions, délais, remarques...') }}"></textarea>
            </div>
        </div>

        <div class="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
            <a class="btn btn-secondary" href="{{ route('tenant.achats.suppliers.index', ['tenant' => $tenantCode]) }}">{{ __('Retour') }}</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $supplierId ? __('Mettre à jour') : __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
            </button>
        </div>
    </div>
</div>
