/*
 * inputsformFile — the behaviour behind <x-inputsform.file>.
 *
 * A drop zone over a native file input: the input keeps the `wire:model`, so
 * Livewire uploads exactly as it always does, and the zone only adds the
 * preview and the drag. It registers on the Alpine that Livewire brings,
 * NEVER by importing Alpine here.
 */

function fileFieldData({ preview = null } = {}) {
    return {
        dragging: false,
        uploading: false,
        progress: 0,

        // What the zone shows. The file just picked wins over the stored one:
        // the person has to see what they chose before saving it.
        preview,
        name: '',

        // Held to be revoked on the next pick: without it a field picked over
        // and over keeps every file it ever showed alive in memory.
        objectUrl: null,

        pick(event) {
            this.show(event.target.files[0] ?? null);
        },

        /**
         * A dropped file is handed to the picker, not read straight from the
         * event: the input is the field Livewire listens to, so this is what
         * makes dragging travel the same road as choosing.
         */
        drop(event) {
            this.dragging = false;

            const file = event.dataTransfer?.files?.[0] ?? null;

            if (! file) {
                return;
            }

            const box = new DataTransfer();
            box.items.add(file);

            this.$refs.picker.files = box.files;
            this.$refs.picker.dispatchEvent(new Event('change', { bubbles: true }));
        },

        show(file) {
            if (! file) {
                return;
            }

            if (this.objectUrl) {
                URL.revokeObjectURL(this.objectUrl);
            }

            this.name = file.name;
            this.objectUrl = URL.createObjectURL(file);
            this.preview = this.objectUrl;
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('inputsformFile', fileFieldData);
});
