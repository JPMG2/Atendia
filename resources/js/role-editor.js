/*
 * roleEditor — the role matrix painted as the admin menu.
 * The ticked permissions live here so a switch answers at once, and every
 * change is pushed to the Livewire form WITHOUT a request: the server reads
 * them once, when the role is saved.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('roleEditor', ({ wire, saved, dependents, rows, groups, labels, isNew, holders, screenNames }) => ({
        perms: [...saved],
        saved: [...saved],
        isNew,
        previewing: false,

        // A starting point replaces the switches wholesale, like a fresh role.
        load(detail) {
            this.perms = [...new Set(detail.permissions)];
            this.push();
        },

        has(permission) {
            return this.perms.includes(permission);
        },

        was(permission) {
            return this.saved.includes(permission);
        },

        // What the preview paints for a row: 'on' | 'new' | 'gone' | 'off'.
        // A role not yet saved has nothing to compare with, so nothing is "new".
        screenState(permissions) {
            const now = permissions.some((permission) => this.has(permission));
            const before = permissions.some((permission) => this.was(permission));

            if (now) return before || this.isNew ? 'on' : 'new';

            return before ? 'gone' : 'off';
        },

        // The screens that would be taken from, or given to, the people who hold the role.
        changedNames(gain) {
            const moved = [...new Set(rows)].filter((permission) => (gain ? this.has(permission) && !this.was(permission) : this.was(permission) && !this.has(permission)));

            return [...new Set(moved.flatMap((permission) => screenNames[permission] ?? []))];
        },

        get people() {
            return holders === 1 ? labels.personOne : labels.personMany.replace(':count', holders);
        },

        get impact() {
            if (this.isNew || holders === 0) return [];

            const lines = [];
            const lost = this.changedNames(false);
            const gained = this.changedNames(true);

            if (lost.length) lines.push(labels.removes.replace(':screens', lost.join(', ')).replace(':people', this.people));
            if (gained.length) lines.push(labels.adds.replace(':screens', gained.join(', ')).replace(':people', this.people));

            return lines;
        },

        push() {
            wire.$set('form.permissions', [...this.perms], false);
        },

        // Switching a screen off takes its actions with it: an action on a
        // closed screen would be a key to a door nobody can reach.
        apply(permission, on) {
            const gone = [permission, ...(dependents[permission] ?? [])];

            this.perms = on
                ? [...new Set([...this.perms, permission])]
                : this.perms.filter((held) => !gone.includes(held));
        },

        set(permission, on) {
            this.apply(permission, on);
            this.push();
        },

        setMany(permissions, on) {
            permissions.forEach((permission) => this.apply(permission, on));
            this.push();
        },

        setAll(on) {
            this.setMany(rows, on);
        },

        // 'all' | 'some' | 'none' for a group switch.
        state(permissions) {
            const held = permissions.filter((permission) => this.has(permission)).length;

            return held === 0 ? 'none' : held === permissions.length ? 'all' : 'some';
        },

        get open() {
            return rows.filter((permission) => this.has(permission)).length;
        },

        get total() {
            return rows.length;
        },

        get percent() {
            return this.total === 0 ? 0 : Math.round((100 * this.open) / this.total);
        },

        get summary() {
            return groups
                .map((group) => ({
                    label: group.label,
                    on: group.screens.filter((permission) => this.has(permission)).length,
                    of: group.screens.length,
                }))
                .filter((group) => group.on > 0);
        },

        get changes() {
            const added = this.perms.filter((permission) => !this.saved.includes(permission));
            const removed = this.saved.filter((permission) => !this.perms.includes(permission));

            return added.length + removed.length;
        },

        get dirty() {
            return this.changes > 0;
        },

        get message() {
            if (this.changes === 0) return this.isNew ? labels.unsavedNew : labels.clean;

            return this.changes === 1 ? labels.unsavedOne : labels.unsavedMany.replace(':count', this.changes);
        },

        // Taking a screen from people who use it today is the one save worth a second look.
        async confirmSave() {
            const lost = this.changedNames(false);

            if (!this.isNew && holders > 0 && lost.length > 0) {
                const ok = await dialog.confirm({
                    title: labels.confirmTitle,
                    message: labels.removes.replace(':screens', lost.join(', ')).replace(':people', this.people),
                    accept: labels.confirmAccept,
                    type: 'warning',
                });

                if (!ok) return;
            }

            wire.save();
        },

        // The server saved: what is on screen is now what is stored.
        settle(detail) {
            this.saved = [...detail.permissions];
            this.perms = [...detail.permissions];
            this.isNew = false;
        },
    }));
});
