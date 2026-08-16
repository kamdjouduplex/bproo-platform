{{-- Photo upload field. Expects Livewire props: photoFile, removePhoto; optional photoUrl / photoPath --}}
<div class="form-span-2">
    <label class="label">Photo de profil</label>
    <div style="display:flex; flex-wrap:wrap; gap:14px; align-items:flex-start;">
        @php
            $preview = \School\Support\StudentPhotoStorage::temporaryPreview($photoFile ?? null)
                ?: ($photoUrl ?? null);
        @endphp
        @if($preview)
            <img src="{{ $preview }}" alt="Photo" style="width:96px; height:120px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0; background:#fff;">
        @else
            <div style="width:96px; height:120px; border-radius:10px; border:1px dashed #cbd5e1; background:#f8fafc; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:11px; text-align:center; padding:8px;">Aucune photo</div>
        @endif
        <div style="flex:1; min-width:220px;">
            <input class="input" type="file" wire:model="{{ $wireModel ?? 'photoFile' }}" accept="image/jpeg,image/png,image/webp,image/gif">
            <div wire:loading wire:target="{{ $wireModel ?? 'photoFile' }}" style="font-size:12px; color:#64748b; margin-top:4px;">Téléversement en cours…</div>
            @error($wireModel ?? 'photoFile') <span class="text-error">{{ $message }}</span> @enderror
            @if(!empty($photoPath) || !empty($photoUrl))
                <label style="display:inline-flex; align-items:center; gap:6px; margin-top:8px; font-size:13px;">
                    <input type="checkbox" wire:model="{{ $removeModel ?? 'removePhoto' }}"> Retirer la photo actuelle
                </label>
            @endif
            <p style="margin:6px 0 0; font-size:12px; color:#94a3b8;">JPG, PNG, WebP ou GIF — max 4 Mo. Affichée sur la fiche et les cartes ID.</p>
        </div>
    </div>
</div>
