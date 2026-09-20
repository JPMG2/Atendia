/*
 * inputsformDatepicker — the behaviour behind <x-inputsform.datepicker>.
 *
 * Flatpickr drives the visible input (single date or range); the committed
 * value lives in a hidden input carrying the `wire:model`, always ISO:
 * "Y-m-d" for one day, "Y-m-d..Y-m-d" for a range. It registers on the
 * Alpine that Livewire brings, NEVER by importing Alpine here.
 */
import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.css';

function iso(date) {
    return flatpickr.formatDate(date, 'Y-m-d');
}

function datepickerData({ mode = 'single', initial = '' } = {}) {
    return {
        picker: null,
        hasValue: initial !== '',

        init() {
            this.picker = flatpickr(this.$refs.display, {
                mode,
                locale: Spanish,
                // What the owner reads is the house date; the wire travels ISO.
                dateFormat: 'd/m/Y',
                monthSelectorType: 'dropdown',
                // The seed arrives ISO while the display speaks d/m/Y, so it
                // is parsed here once instead of overriding parseDate (which
                // flatpickr also calls with Date objects mid-click).
                defaultDate:
                    initial === '' ? undefined : initial.split('..').map((part) => flatpickr.parseDate(part, 'Y-m-d')),
                onClose: (dates) => this.commit(dates),
            });
        },

        /**
         * A range closed on one day commits that single day: making the owner
         * click the same date twice to filter one day is a papercut.
         */
        commit(dates) {
            // Flatpickr wipes an incomplete range right after closing on an
            // outside click; completing it as a same-day range keeps the pick,
            // shown as the one date it is.
            if (mode === 'range' && dates.length === 1) {
                this.picker.setDate([dates[0], dates[0]], false);
                this.$refs.display.value = flatpickr.formatDate(dates[0], 'd/m/Y');
            }

            const value =
                dates.length === 0 ? '' : dates.length === 1 ? iso(dates[0]) : `${iso(dates[0])}..${iso(dates[1])}`;

            this.hasValue = value !== '';

            const field = this.$refs.value;

            if (field.value === value) {
                return;
            }

            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        },

        clear() {
            this.picker.clear();
            this.commit([]);
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('inputsformDatepicker', datepickerData);
});
