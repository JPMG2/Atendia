/*
 * dialog — the ONLY way to notify, warn, confirm or offer a retry.
 *
 * GOLDEN RULE: AtendIa has no native browser alerts — they cannot be themed,
 * ignore the app's language and read as a system error. It is global and
 * returns a PROMISE, so a caller reads as ordinary code:
 *
 *   if (! await dialog.confirm({ title: '...', message: '...' })) {
 *       return;
 *   }
 *
 * The window is drawn by <livewire:dialog />, mounted ONCE in the layout.
 */

/**
 * Queues a dialog and waits for the answer.
 *
 * The `resolve` travels in the event detail: that is how a promise reaches
 * the Alpine component drawing the window.
 */
function open(options) {
    return new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('dialog', { detail: { ...options, resolve } }));
    });
}

window.dialog = {
    /** A notice: one button, nothing to decide. */
    notify: (options = {}) => open({ mode: 'notify', type: 'info', ...options }),

    /** A question: cancel or accept. `type: 'danger'` for what cannot be undone. */
    confirm: (options = {}) => open({ mode: 'confirm', type: 'warning', ...options }),

    /**
     * Something failed and can be tried again.
     *
     * `warning` and not `danger`: retrying destroys nothing, and a red button
     * there reads as "this breaks something" right when it has to be pressed.
     */
    retry: (options = {}) => open({ mode: 'retry', type: 'warning', ...options }),
};

/**
 * The host: it keeps the queue and resolves the promise of the dialog on
 * screen. There is a queue because two notices can overlap, and losing the
 * second is worse than making it wait.
 */
export function dialogHost({ labels = {} } = {}) {
    return {
        labels,
        queue: [],
        current: null,

        push(detail) {
            this.queue.push(detail);

            if (this.current === null) {
                this.show();
            }
        },

        show() {
            this.current = this.queue.shift() ?? null;

            // The page behind does not scroll: chasing it with a dialog open is
            // disorienting.
            document.body.classList.toggle('has-dialog', this.current !== null);

            if (this.current === null) {
                return;
            }

            // Focus starts on the action, not behind: Enter answers and the screen
            // reader announces what this is about.
            this.$nextTick(() => this.$refs.accept?.focus());
        },

        /** The action's label: whatever the caller asked for, or the mode's. */
        acceptLabel() {
            if (this.current?.accept) {
                return this.current.accept;
            }

            return {
                notify: this.labels.understood,
                retry: this.labels.retry,
            }[this.current?.mode] ?? this.labels.accept;
        },

        cancelLabel() {
            return this.current?.cancel ?? this.labels.cancel;
        },

        accept() {
            this.answer(true);
        },

        /** Escape, a click outside and the cancel button all mean the same: no. */
        cancel() {
            this.answer(false);
        },

        answer(value) {
            const resolve = this.current?.resolve;

            this.current = null;
            resolve?.(value);

            this.show();
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('dialogHost', dialogHost);
});
