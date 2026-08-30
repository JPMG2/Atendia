/*
 * catalogMaster — the Alpine rail of ANY master in the catalog hub.
 *
 * Every editor carried this block with the names changed, so a fix in one
 * stayed broken in the others. Each master passes its own config:
 *
 *   catalogMaster({
 *     items:  [...],                       // rows, handed over once at mount
 *     path:   'form.data',                 // where the DTO lives in the component
 *     search: ['code', 'name'],            // keys the search box filters by
 *     rules:  { code: ['required', ...] }, // mirror of getValidationRules()
 *   })
 *
 * It needs the global `validate()` from form-guard.js.
 */
export function catalogMaster({ items = [], path = '', search = [], rules = {} } = {}) {
    return {
        view: 'list',
        mode: 'create',
        q: '',
        errors: {},
        items,

        // The row being edited, ONLY so the header can name it. The fields do
        // not come from here: they go through wire:model against the server's
        // DTO, which is the form's only state.
        current: null,

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
            this.current = null;

            // The server has to start blank too, or a new record inherits the
            // data and the id of the one edited before.
            await this.$wire.openCreate();
            this.view = 'form';
        },

        async openEdit(row) {
            // Mind the order: `mode` and `current` are set BEFORE the await. Set
            // after, the window between the click and the response still holds the
            // previous mode, and a form opened from "new" says the wrong thing.
            // Only `view` waits for the server.
            this.mode = 'edit';
            this.errors = {};
            this.current = row;

            if (!(await this.$wire.openEdit(row.id))) {
                return;
            }

            this.view = 'form';
        },

        backToList() {
            this.view = 'list';
        },

        async submit() {
            // Mirror of the Form's getValidationRules(). What cannot be
            // replicated here is whatever needs the database — that bounce still
            // comes from the server.
            const values = {};
            for (const field in rules) {
                values[field] = this.$wire.get(`${path}.${field}`);
            }

            this.errors = validate(values, rules);

            if (Object.keys(this.errors).length > 0) {
                return;
            }

            // On a save the server already blanked the form, so going back to the
            // list is the only option — otherwise the person stares at an empty
            // form still titled "edit". On a failure they keep what they typed.
            const saved = this.mode === 'edit' ? await this.$wire.update() : await this.$wire.create();

            if (saved) {
                this.backToList();
            }
        },

        remove() {
            // Deleting is not wired into any master yet.
            this.backToList();
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('catalogMaster', catalogMaster);
});
