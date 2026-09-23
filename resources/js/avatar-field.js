/*
 * inputsformAvatar — the behaviour behind <x-inputsform.avatar>.
 *
 * Cropper.js frames the picked photo in a square; only the cropped canvas
 * uploads (through the component's $wire), so a 12 MB phone photo travels
 * as a small WebP. It registers on the Alpine Livewire brings, never its own.
 */
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const OUTPUT_SIZE = 512;

function avatarData({ preview = null, model = null, field = null } = {}) {
    return {
        preview,
        field,
        cropping: false,
        uploading: false,
        cropper: null,
        objectUrl: null,

        pick(event) {
            const file = event.target.files?.[0];

            if (!file) {
                return;
            }

            this.cropping = true;
            this.objectUrl = URL.createObjectURL(file);

            // Built once the image has loaded AND its panel has painted (two
            // frames): Cropper sizes itself from its container, and a panel
            // x-show just revealed still measures zero on the first one.
            this.$refs.stage.onload = () => requestAnimationFrame(() => requestAnimationFrame(() => {
                this.cropper?.destroy();
                this.cropper = new Cropper(this.$refs.stage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    background: false,
                });
            }));

            this.$nextTick(() => {
                this.$refs.stage.src = this.objectUrl;
            });
        },

        apply() {
            const canvas = this.cropper.getCroppedCanvas({ width: OUTPUT_SIZE, height: OUTPUT_SIZE });

            canvas.toBlob(
                (blob) => {
                    const file = new File([blob], 'avatar.webp', { type: 'image/webp' });
                    this.uploading = true;

                    this.$wire.upload(
                        model,
                        file,
                        () => {
                            this.uploading = false;
                            this.preview = canvas.toDataURL('image/webp');
                            this.close();
                            // The card's unsaved pill listens for a change.
                            this.$el.dispatchEvent(new Event('change', { bubbles: true }));
                        },
                        () => {
                            this.uploading = false;
                        },
                    );
                },
                'image/webp',
                0.9,
            );
        },

        cancel() {
            this.close();
        },

        close() {
            this.cropper?.destroy();
            this.cropper = null;
            this.cropping = false;
            this.$refs.picker.value = '';

            if (this.objectUrl) {
                URL.revokeObjectURL(this.objectUrl);
                this.objectUrl = null;
            }
        },

        /** Removing is the SCREEN's decision: this only announces it. */
        remove() {
            this.$dispatch('file-remove', { name: this.field });
        },

        reset() {
            this.preview = null;
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('inputsformAvatar', avatarData);
});
