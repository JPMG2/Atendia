/*
 * inputsformPhone — the split phone control: a dial picker plus the national
 * number, composed into ONE hidden field ("+58 4247673951") where `wire:model`
 * lives. The dial is not a native <select>: the browser owns that list and can
 * pick an option on its own; here nothing is chosen until a click or Enter.
 */
import { fold } from './combobox.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('inputsformPhone', ({ value, defaultDial, countries }) => ({
        dial: '',
        number: '',
        picked: null,
        open: false,
        query: '',
        highlighted: 0,

        init() {
            const dials = countries.map((country) => country.code);
            const raw = (value ?? '').trim();
            const match = raw.match(/^\+(\d{1,4})\s+(.*)$/);
            // A screen that stores plain digits hands them back as "+" and digits, no space:
            // the dial is the longest known one the digits start with.
            const glued = raw.match(/^\+(\d+)$/);
            const gluedDial = glued ? dials.filter((dial) => glued[1].startsWith(dial)).sort((a, b) => b.length - a.length)[0] : undefined;

            if (match && dials.includes(match[1])) {
                this.dial = match[1];
                this.number = match[2].replace(/\D/g, '');
            } else if (gluedDial) {
                this.dial = gluedDial;
                this.number = glued[1].slice(gluedDial.length);
            } else {
                this.dial = dials.includes(defaultDial) ? defaultDial : (dials[0] ?? '');
                this.number = raw.replace(/\D/g, '');
            }

            // Several countries share a dial (+1): the first one stands for it.
            this.picked = countries.find((country) => country.code === this.dial) ?? null;

            this.$watch('dial', () => this.sync());
            this.$watch('number', () => this.sync());
        },

        /** Name or dial, flat and without accents; the "+" typed by hand is ignored. */
        filtered() {
            const needle = fold(this.query).replace(/^\+/, '').trim();

            if (needle === '') {
                return countries;
            }

            return countries.filter((country) => fold(country.name).includes(needle) || country.code.startsWith(needle));
        },

        toggle() {
            this.open ? this.closePanel() : this.openPanel();
        },

        openPanel() {
            this.query = '';
            this.open = true;
            this.highlighted = Math.max(0, countries.findIndex((country) => country === this.picked));

            this.$nextTick(() => {
                this.$refs.search?.focus();
                this.scrollToHighlighted();
            });
        },

        closePanel() {
            this.open = false;
        },

        choose(country) {
            this.picked = country;
            this.dial = country.code;
            this.closePanel();
            this.$refs.number?.focus();
        },

        chooseHighlighted() {
            const country = this.filtered()[this.highlighted];

            if (country) {
                this.choose(country);
            }
        },

        move(step) {
            const total = this.filtered().length;

            if (total === 0) {
                return;
            }

            this.highlighted = (this.highlighted + step + total) % total;
            this.scrollToHighlighted();
        },

        scrollToHighlighted() {
            this.$nextTick(() => {
                this.$refs.list?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
            });
        },

        isPicked(country) {
            return this.picked === country;
        },

        sync() {
            // Digits only in the national part: the dial is the picker's job.
            this.number = this.number.replace(/\D/g, '');

            const composed = this.number === '' ? '' : `+${this.dial} ${this.number}`;
            const real = this.$refs.real;

            if (real.value !== composed) {
                real.value = composed;
                real.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    }));
});
