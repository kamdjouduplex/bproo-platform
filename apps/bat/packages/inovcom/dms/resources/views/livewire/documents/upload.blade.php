@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div>
    <form wire:submit="save" autocomplete="off">
        <section class="card max-w-2xl">
            <h2 class="text-base font-semibold text-slate-800 mb-5">{{ __('Téléverser un document') }}</h2>

            <div class="grid grid-cols-1 gap-4">

                <div class="field">
                    <label class="field-label">{{ __('Fichier') }} <span class="text-red-500">*</span></label>
                    <div x-data="{ dragging: false }"
                         @dragover.prevent="dragging = true"
                         @dragleave.prevent="dragging = false"
                         @drop.prevent="dragging = false; $wire.upload('file', $event.dataTransfer.files[0])"
                         :class="dragging ? 'border-blue-400 bg-blue-50' : 'border-slate-300 bg-slate-50 hover:border-slate-400'"
                         class="border-2 border-dashed rounded-xl transition-colors cursor-pointer">
                        <input type="file" wire:model="file" class="sr-only" id="dms-file">
                        <label for="dms-file" class="flex flex-col items-center gap-2 py-8 px-4 cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="32" height="32" class="text-slate-400">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span wire:loading wire:target="file" class="text-blue-600 text-[13px]">{{ __('Téléversement...') }}</span>
                            <span wire:loading.remove wire:target="file" class="text-center">
                                @if($file)
                                    <span class="text-[13px] font-semibold text-slate-700">{{ $file->getClientOriginalName() }}</span>
                                    <span class="block text-[12px] text-slate-400">
                                        ({{ round($file->getSize() / 1024, 1) }} KB)
                                    </span>
                                @else
                                    <span class="text-[13px] text-slate-500">{{ __('Glisser-déposer ou cliquer pour sélectionner') }}</span>
                                    <span class="block text-[12px] text-slate-400">{{ __('Max 20 MB') }}</span>
                                @endif
                            </span>
                        </label>
                    </div>
                    @error('file') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Titre') }} <span class="text-red-500">*</span></label>
                    <input type="text" class="input" wire:model="title">
                    @error('title') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Catégorie') }}</label>
                    <select class="input" wire:model="category">
                        <option value="other">{{ __('Autre') }}</option>
                        <option value="contract">{{ __('Contrat') }}</option>
                        <option value="plan">{{ __('Plan') }}</option>
                        <option value="permit">{{ __('Permis') }}</option>
                        <option value="photo">{{ __('Photo') }}</option>
                        <option value="report">{{ __('Rapport') }}</option>
                        <option value="invoice">{{ __('Facture') }}</option>
                        <option value="quote">{{ __('Devis') }}</option>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label">{{ __('Description') }}</label>
                    <textarea class="input" rows="3" wire:model="description"></textarea>
                </div>

            </div>

            <div class="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
                <a href="{{ route('tenant.dms.index', ['tenant' => $tenantCode]) }}" class="btn btn-secondary">{{ __('Annuler') }}</a>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,file">
                    <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                    <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
                </button>
            </div>
        </section>
    </form>
</div>
