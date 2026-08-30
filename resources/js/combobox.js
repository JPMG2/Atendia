/*
 * inputsformCombobox — the behaviour behind <x-inputsform.combobox>.
 *
 * An autocompleting select: it filters as you type, with no native <select>,
 * which can be neither themed nor filtered. The real value lives in a hidden
 * input carrying the `wire:model`. It registers on the Alpine that Livewire
 * brings, NEVER by importing Alpine here.
 */

/**
 * Normalised for comparison: lowercase and WITHOUT accents, so a query typed
 * flat finds the accented option. Without it the search is useless in Spanish.
 */
function fold(value) {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function comboboxData({ options = [], initial = null } = {}) {
    return {
        options,
        open: false,
        query: '',
        // The chosen option as a whole object, or null. The hidden input keeps only its value.
        selected: options.find((option) => String(option.value) === String(initial)) ?? null,
        highlighted: 0,

        init() {
            this.query = this.selected ? this.selected.label : '';
        },

        /** What the input shows: while open, whatever is being typed wins. */
        filtered() {
            const needle = fold(this.query).trim();

            // Just opened, with the text still equal to the chosen option, nothing
            // is filtered: otherwise opening the field would show that one option
            // and there would be no way to change it without erasing by hand.
            if (needle === '' || (this.selected && needle === fold(this.selected.label))) {
                return this.options;
            }

            return this.options.filter((option) => fold(option.label).includes(needle));
        },

        openPanel() {
            if (this.open) {
                return;
            }

            this.open = true;
            this.highlighted = Math.max(
                0,
                this.filtered().findIndex((option) => option === this.selected),
            );
        },

        closePanel() {
            this.open = false;
            // Half-typed text that chose nothing cannot stay on screen posing as a
            // value: the chosen option's label is put back.
            this.query = this.selected ? this.selected.label : '';
        },

        onInput() {
            this.openPanel();
            this.highlighted = 0;

            // Emptying the field is how you deselect: the hidden input goes to ''
            // and validation reports the required field as it should.
            if (this.query === '' && this.selected !== null) {
                this.commit(null);
            }
        },

        move(step) {
            const total = this.filtered().length;

            if (total === 0) {
                return;
            }

            this.openPanel();
            this.highlighted = (this.highlighted + step + total) % total;
            this.scrollToHighlighted();
        },

        /**
         * Empties the field in one go and leaves it ready to type: having to erase
         * the whole label by hand is why this button exists. It reuses commit() so
         * the hidden input and Livewire find out.
         */
        clear() {
            this.commit(null);
            this.query = '';
            this.$refs.search.focus();
            this.openPanel();
        },

        choose(option) {
            this.commit(option);
            this.query = option.label;
            this.open = false;
        },

        chooseHighlighted() {
            const option = this.filtered()[this.highlighted];

            if (option) {
                this.choose(option);
            }
        },

        /**
         * Writes the value into the hidden input and fires an `input` event:
         * Livewire listens for the event and not the property, so assigning
         * `.value` alone would leave the server unaware.
         */
        commit(option) {
            this.selected = option;

            const field = this.$refs.value;
            field.value = option ? option.value : '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        },

        scrollToHighlighted() {
            this.$nextTick(() => {
                this.$refs.list?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
            });
        },

        /** Is this the chosen option? Used for the tick in the list. */
        isSelected(option) {
            return this.selected !== null && String(option.value) === String(this.selected.value);
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('inputsformCombobox', comboboxData);
});
