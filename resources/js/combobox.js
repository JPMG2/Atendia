/*
 * inputsformCombobox — comportamiento del <x-inputsform.combobox>.
 *
 * Select con autocompletado: filtra al tipear, se navega con el teclado y no usa
 * un <select> nativo (que no se puede tematizar ni filtrar). El valor real vive
 * en un <input type="hidden"> que lleva el `wire:model`, así Livewire lo recibe
 * exactamente igual que en cualquier otro campo.
 *
 * Se registra sobre el Alpine que trae Livewire (`alpine:init`), NUNCA importando
 * Alpine acá: importarlo arrancaría un segundo Alpine y rompería todo el dashboard.
 */

/**
 * Normaliza para comparar: minúsculas y SIN acentos, así "peru" encuentra "Perú"
 * y "dolar" encuentra "Dólar". Sin esto el buscador es inútil en español.
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
        // Opción elegida (objeto completo) o null. El hidden guarda solo su value.
        selected: options.find((option) => String(option.value) === String(initial)) ?? null,
        highlighted: 0,

        init() {
            this.query = this.selected ? this.selected.label : '';
        },

        /** Lo que se ve en el input: mientras está abierto manda lo que se tipea. */
        filtered() {
            const needle = fold(this.query).trim();

            // Con el panel recién abierto y el texto igual a la opción elegida, no
            // se filtra: si no, abrir el campo mostraría UNA sola opción (la actual)
            // y no se podría cambiar sin borrar a mano.
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
            // Un texto a medio tipear que no eligió nada no puede quedar en pantalla
            // haciéndose pasar por un valor: se restaura la etiqueta de lo elegido.
            this.query = this.selected ? this.selected.label : '';
        },

        onInput() {
            this.openPanel();
            this.highlighted = 0;

            // Vaciar el campo es la forma de deseleccionar: el hidden queda en ''
            // y la validación (front o server) reporta el requerido como corresponde.
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
         * Escribe el valor en el hidden y avisa con un evento `input`: Livewire
         * escucha el evento, no la propiedad, así que asignar `.value` a secas
         * dejaría el server sin enterarse del cambio.
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

        /** ¿Esta opción es la elegida? (para el tilde de la lista) */
        isSelected(option) {
            return this.selected !== null && String(option.value) === String(this.selected.value);
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('inputsformCombobox', comboboxData);
});
