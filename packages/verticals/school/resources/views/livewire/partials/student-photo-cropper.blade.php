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
    x-data="schoolStudentPhotoCrop({
        method: @js($wireMethod),
        aspect: 3/4,
        outputWidth: 480,
        outputHeight: 640
    })"
>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

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
            <p x-show="status" x-text="status" style="margin:6px 0 0; font-size:12px; color:#2563eb;"></p>
            <p x-show="error" x-text="error" style="margin:6px 0 0; font-size:12px; color:#dc2626;"></p>
        </div>
    </div>

    <div
        x-show="open"
        x-cloak
        style="position:fixed; inset:0; z-index:120; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; padding:16px;"
        @keydown.escape.window="open && close()"
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
                <button type="button" class="btn btn-primary" @click="confirm()" :disabled="saving" x-text="saving ? 'Enregistrement…' : 'Valider le cadrage'"></button>
            </div>
        </div>
    </div>
</div>

@once
    <style>
        [x-cloak] { display: none !important; }
        .sch-photo-crop .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
        }
        .sch-photo-crop .cropper-view-box,
        .sch-photo-crop .cropper-face { border-radius: 4px; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        window.schoolStudentPhotoCrop = function (config) {
            return {
                open: false,
                saving: false,
                status: '',
                error: '',
                previewUrl: null,
                cropper: null,
                objectUrl: null,
                method: config.method || 'saveCroppedProfilePhoto',
                aspect: config.aspect || (3 / 4),
                outputWidth: config.outputWidth || 480,
                outputHeight: config.outputHeight || 640,

                onFile(event) {
                    this.error = '';
                    this.status = '';
                    const file = event.target.files && event.target.files[0];
                    event.target.value = '';
                    if (!file) return;
                    if (!/^image\//.test(file.type)) {
                        this.error = 'Choisissez une image (JPG, PNG, WebP…).';
                        return;
                    }
                    if (file.size > 8 * 1024 * 1024) {
                        this.error = 'Fichier trop lourd (max 8 Mo avant cadrage).';
                        return;
                    }
                    this.destroyCropper();
                    if (this.objectUrl) URL.revokeObjectURL(this.objectUrl);
                    this.objectUrl = URL.createObjectURL(file);
                    this.open = true;
                    this.$nextTick(() => {
                        const img = this.$refs.image;
                        img.onload = () => this.initCropper();
                        img.src = this.objectUrl;
                    });
                },

                initCropper() {
                    if (typeof Cropper === 'undefined') {
                        this.error = 'Outil de cadrage indisponible (Cropper.js).';
                        this.open = false;
                        return;
                    }
                    this.destroyCropper();
                    this.cropper = new Cropper(this.$refs.image, {
                        aspectRatio: this.aspect,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.88,
                        responsive: true,
                        background: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                        wheelZoomRatio: 0.08,
                    });
                },

                destroyCropper() {
                    if (this.cropper) {
                        this.cropper.destroy();
                        this.cropper = null;
                    }
                },

                zoom(delta) { if (this.cropper) this.cropper.zoom(delta); },
                rotate(deg) { if (this.cropper) this.cropper.rotate(deg); },
                reset() { if (this.cropper) this.cropper.reset(); },

                close() {
                    this.open = false;
                    this.destroyCropper();
                    if (this.objectUrl) {
                        URL.revokeObjectURL(this.objectUrl);
                        this.objectUrl = null;
                    }
                },

                clearLocalPreview() {
                    this.previewUrl = null;
                    this.status = '';
                },

                async confirm() {
                    if (!this.cropper || this.saving) return;
                    this.saving = true;
                    this.error = '';
                    try {
                        const canvas = this.cropper.getCroppedCanvas({
                            width: this.outputWidth,
                            height: this.outputHeight,
                            imageSmoothingEnabled: true,
                            imageSmoothingQuality: 'high',
                            fillColor: '#ffffff',
                        });
                        if (!canvas) throw new Error('Cadrage impossible');
                        const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
                        this.previewUrl = dataUrl;
                        if (this.$wire && typeof this.$wire[this.method] === 'function') {
                            await this.$wire[this.method](dataUrl);
                            this.status = 'Photo cadrée enregistrée.';
                        }
                        this.close();
                    } catch (e) {
                        this.error = (e && e.message) ? e.message : 'Échec de l’enregistrement.';
                    } finally {
                        this.saving = false;
                    }
                },
            };
        };
    </script>
@endonce
