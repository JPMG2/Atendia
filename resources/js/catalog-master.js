/*
 * catalogMaster — el riel de Alpine de CUALQUIER maestro del hub de catálogos.
 *
 * Los tres editores (monedas, países, redes sociales) tenían este mismo bloque
 * copiado con los nombres cambiados: mismo view/mode/f/errors, mismo filtered(),
 * mismo openCreate/openEdit, mismo submit. Un bug arreglado en uno seguía vivo
 * en los otros dos. Acá vive una sola vez y cada maestro pasa su config:
 *
 *   catalogMaster({
 *     items:  [...],                       // filas, entregadas una sola vez al montar
 *     path:   'form.data',                 // dónde vive el DTO en el componente Livewire
 *     blank:  { code:'', name:'' },        // estado de `f` para un alta
 *     search: ['code', 'name'],            // claves por las que filtra el buscador
 *     rules:  { code: ['required', ...] }, // espejo de getValidationRules()
 *   })
 *
 * Depende de la función madre global `validate()` (form-guard.js), que ya se
 * carga en el layout del dashboard.
 */
export function catalogMaster({ items = [], path = '', blank = {}, search = [], rules = {} } = {}) {
    return {
        view: 'list',
        mode: 'create',
        q: '',
        errors: {},
        items,

        // id === null => alta. Con id => edición de ESA fila, pase lo que pase
        // con el código o el nombre (los dos son editables por el usuario).
        f: { id: null, ...blank },

        filtered() {
            const q = this.q.trim().toLowerCase();
            if (!q) return this.items;

            return this.items.filter((row) =>
                search.some((key) => String(row[key] ?? '').toLowerCase().includes(q)),
            );
        },

        async openCreate() {
            this.mode = 'create';
            this.errors = {};
            this.f = { id: null, ...blank };

            // El server también tiene que arrancar en blanco, si no el alta
            // hereda los datos y el id del registro que se editó antes.
            await this.$wire.openCreate();
            this.view = 'form';
        },

        async openEdit(row) {
            // OJO con el orden: `mode` y `f` se setean YA, antes del await, igual
            // que en openCreate(). Si se setean después, entre el click y la
            // respuesta del server queda una ventana con el mode de la vez
            // anterior — y si venías de "Crear", el form abre diciendo "Nueva".
            // Lo único que espera al server es `view`, para no mostrar el
            // formulario de un registro que ya no existe.
            this.mode = 'edit';
            this.errors = {};
            this.f = { ...row };

            if (!(await this.$wire.openEdit(row.id))) {
                return;
            }

            this.view = 'form';
        },

        backToList() {
            this.view = 'list';
        },

        async submit() {
            // Espejo de getValidationRules() del Form. Lo que NO se puede
            // replicar acá es lo que necesita la base (`unique`, `exists`, `in`
            // contra un catálogo): ese rebote sigue viniendo del server.
            const values = {};
            for (const field in rules) {
                values[field] = this.$wire.get(`${path}.${field}`);
            }

            this.errors = validate(values, rules);

            if (Object.keys(this.errors).length > 0) {
                return;
            }

            // Si guardó, el server ya vació el form: hay que volver a la lista o
            // el usuario se queda mirando un formulario en blanco que todavía
            // dice "Editar ARS". Si no guardó, se queda con lo que escribió.
            const saved = this.mode === 'edit' ? await this.$wire.update() : await this.$wire.create();

            if (saved) {
                this.backToList();
            }
        },

        remove() {
            // La baja todavía no está cableada en ningún maestro.
            this.backToList();
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('catalogMaster', catalogMaster);
});
