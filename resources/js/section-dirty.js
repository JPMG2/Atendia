/*
 * sectionDirty — the amber "unsaved changes" pill on the profile cards: any
 * keystroke in a card lights it, and only THAT card's successful save (the
 * toast it fires) puts it out. Born when the owner typed a description,
 * never hit the card's own Guardar and lost it (2026-09-16).
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('sectionDirty', () => ({
        dirty: false,
        pendingSave: false,

        markDirty() {
            this.dirty = true;
        },

        // The click bubbles up from whatever button lives in the actions
        // row, front guards included: no per-button wiring to forget.
        trackSave(event) {
            if (!event.target.closest('.bp-card-actions')) return;

            this.pendingSave = true;

            // A blocked front guard sends no request and no toast: the mark
            // must not linger to swallow another card's success later.
            setTimeout(() => (this.pendingSave = false), 8000);
        },

        settle(detail) {
            if (!this.pendingSave) return;

            if (detail?.type === 'success') this.dirty = false;

            this.pendingSave = false;
        },
    }));
});
