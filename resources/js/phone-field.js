/*
 * inputsformPhone — the split phone control: a country-dial select plus the
 * national number, composed into ONE hidden field ("+58 4247673951") where
 * `wire:model` lives, so the server keeps seeing a single column.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('inputsformPhone', ({ value, defaultDial, dials }) => ({
        dial: '',
        number: '',

        init() {
            const raw = (value ?? '').trim();
            const match = raw.match(/^\+(\d{1,4})\s+(.*)$/);

            if (match && dials.includes(match[1])) {
                this.dial = match[1];
                this.number = match[2].replace(/\D/g, '');
            } else {
                this.dial = dials.includes(defaultDial) ? defaultDial : (dials[0] ?? '');
                this.number = raw.replace(/\D/g, '');
            }

            this.$watch('dial', () => this.sync());
            this.$watch('number', () => this.sync());
        },

        sync() {
            // Digits only in the national part: the dial is the select's job.
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
