{{--
  Interactive portrait cropper for student photos.
  Props: wireMethod, currentUrl?, buttonLabel?
--}}
@php
    $wireMethod = $wireMethod ?? 'saveCroppedProfilePhoto';
    $currentUrl = $currentUrl ?? null;
    $buttonLabel = $buttonLabel ?? 'Choisir et cadrer la photo';
@endphp

<div
    class="sch-photo-crop"
    wire:ignore.self
    x-data="(window.schoolStudentPhotoCrop || function () { return { open: false, previewUrl: null, status: '', error: 'Rechargez la page pour cadrer une photo.', onFile() {}, close() { this.open = false }, clearLocalPreview() {}, confirm() {}, zoom() {}, rotate() {}, reset() {} }; })({
        method: @js($wireMethod),
        aspect: 3/4,
        outputWidth: 480,
        outputHeight: 640
    })"
>
    <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start;">
        <div>
            <template x-if="previewUrl">
                <img :src="previewUrl" alt="Aperçu" style="width:112px; height:140px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0; background:#fff;">
            </template>
            <template x-if="!previewUrl">
                @if($currentUrl)
                    <img src="{{ $currentUrl }}" alt="Photo actuelle" style="width:112px; height:140px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0;">
                @else
                    <div style="width:112px; height:140px; border-radius:10px; border:1px dashed #cbd5e1; background:#f8fafc; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:12px; text-align:center; padding:8px;">Aucune photo</div>
                @endif
            </template>
        </div>
        <div style="flex:1; min-width:220px;">
            <input type="file" x-ref="fileInput" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif" @change="onFile($event)">
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <button type="button" class="btn btn-primary btn-sm" @click="$refs.fileInput.click()">{{ $buttonLabel }}</button>
                <button type="button" class="btn btn-secondary btn-sm" x-show="previewUrl" x-cloak @click="clearLocalPreview()">Annuler l’aperçu</button>
            </div>
            <p style="margin:8px 0 0; font-size:12px; color:#94a3b8;">
                Format portrait <strong>3:4</strong> (cartes ID). Zoomez et centrez le visage avant de valider.
            </p>
            <p x-show="status" x-cloak x-text="status" style="margin:6px 0 0; font-size:12px; color:#2563eb;"></p>
            <p x-show="error" x-cloak x-text="error" style="margin:6px 0 0; font-size:12px; color:#dc2626;"></p>
        </div>
    </div>

    <div
        class="sch-photo-crop__overlay"
        x-cloak
        :class="{ 'is-open': open }"
        @keydown.escape.window="open && close()"
        @click.self="close()"
    >
        <div style="background:#fff; border-radius:12px; width:min(720px, 96vw); max-height:92vh; overflow:auto; box-shadow:0 20px 50px rgba(0,0,0,.25);" @click.stop>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid #e2e8f0;">
                <div>
                    <div style="font-weight:700; font-size:15px;">Cadrer la photo</div>
                    <div style="font-size:12px; color:#64748b;">Placez le visage dans le cadre portrait</div>
                </div>
                <button type="button" class="sch-modal__close" @click="close()" aria-label="Fermer">&times;</button>
            </div>

            <div style="padding:12px 16px;">
                <div style="max-height:min(52vh, 420px); background:#0f172a; border-radius:8px; overflow:hidden;">
                    <img x-ref="image" alt="À cadrer" style="display:block; max-width:100%;">
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:12px;">
                    <button type="button" class="btn btn-secondary btn-sm" @click="zoom(0.1)">Zoom +</button>
                    <button type="button" class="btn btn-secondary btn-sm" @click="zoom(-0.1)">Zoom −</button>
                    <button type="button" class="btn btn-secondary btn-sm" @click="rotate(-90)">−90°</button>
                    <button type="button" class="btn btn-secondary btn-sm" @click="rotate(90)">+90°</button>
                    <button type="button" class="btn btn-secondary btn-sm" @click="reset()">Réinitialiser</button>
                </div>
                <p style="margin:10px 0 0; font-size:12px; color:#64748b;">Glissez pour centrer · molette pour zoomer · redimensionnez le cadre si besoin.</p>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; padding:12px 16px; border-top:1px solid #e2e8f0;">
                <button type="button" class="btn btn-secondary" @click="close()">Annuler</button>
                <button type="button" class="btn btn-primary" @click="confirm()" :disabled="saving" x-text="saving ? 'Enregistrement…' : 'Valider le cadrage'">Valider le cadrage</button>
            </div>
        </div>
    </div>
</div>
