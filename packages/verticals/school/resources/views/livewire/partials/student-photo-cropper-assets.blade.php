{{-- Loaded on the students pages (not inside the create modal) so Cropper + Alpine exist before "Nouvel élève". --}}
@once
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        [x-cloak] { display: none !important; }
        .sch-photo-crop .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
        }
        .sch-photo-crop .cropper-view-box,
        .sch-photo-crop .cropper-face { border-radius: 4px; }
        .sch-photo-crop__overlay {
            position: fixed;
            inset: 0;
            z-index: 200;
            background: rgba(15, 23, 42, .55);
            align-items: center;
            justify-content: center;
            padding: 16px;
            display: none;
        }
        .sch-photo-crop__overlay.is-open {
            display: flex !important;
        }
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
                method: (config && config.method) || 'saveCroppedProfilePhoto',
                aspect: (config && config.aspect) || (3 / 4),
                outputWidth: (config && config.outputWidth) || 480,
                outputHeight: (config && config.outputHeight) || 640,

                onFile(event) {
                    this.error = '';
                    this.status = '';
                    const file = event.target.files && event.target.files[0];
                    event.target.value = '';
                    if (!file) {
                        return;
                    }
                    if (!/^image\//.test(file.type)) {
                        this.error = 'Choisissez une image (JPG, PNG, WebP…).';
                        return;
                    }
                    if (file.size > 8 * 1024 * 1024) {
                        this.error = 'Fichier trop lourd (max 8 Mo avant cadrage).';
                        return;
                    }
                    this.destroyCropper();
                    if (this.objectUrl) {
                        URL.revokeObjectURL(this.objectUrl);
                    }
                    this.objectUrl = URL.createObjectURL(file);
                    this.open = true;
                    this.$nextTick(() => {
                        const img = this.$refs.image;
                        if (!img) {
                            return;
                        }
                        img.onload = () => this.initCropper();
                        img.onerror = () => {
                            this.error = 'Impossible de lire cette image.';
                            this.close();
                        };
                        img.src = this.objectUrl;
                    });
                },

                initCropper() {
                    if (typeof Cropper === 'undefined') {
                        this.error = 'Outil de cadrage indisponible (Cropper.js).';
                        this.close();
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
                    const img = this.$refs.image;
                    if (img) {
                        img.onload = null;
                        img.onerror = null;
                        img.removeAttribute('src');
                    }
                },

                clearLocalPreview() {
                    this.previewUrl = null;
                    this.status = '';
                },

                async confirm() {
                    if (!this.cropper || this.saving) {
                        return;
                    }
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
                        if (!canvas) {
                            throw new Error('Cadrage impossible');
                        }
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
