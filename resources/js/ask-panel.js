/**
 * The side panel of both AI assistants (client and admin): opening it, asking
 * a question and keeping the thread scrolled to the end. The Livewire
 * component owns the data (`opened`, `ask()`, `open()`); this owns the panel.
 */
function askPanel() {
    return {
        open: false,
        pending: '',

        show() {
            this.open = true;
            const ready = this.$wire.opened ? Promise.resolve() : this.$wire.open();
            ready.then(() => this.$nextTick(() => document.getElementById('in-question')?.focus()));

            return ready;
        },

        /** The slide-over's X, its backdrop and Escape all say `slide-over-close`: closing is the panel's to do. */
        close() {
            if (this.open) {
                this.open = false;
                this.$wire.close();
            }
        },

        scrollDown() {
            this.$nextTick(() => {
                const thread = document.querySelector('.ask-thread');
                thread?.scrollTo({ top: thread.scrollHeight, behavior: 'smooth' });
            });
        },

        send(suggested) {
            const question = (suggested ?? this.$wire.form.question ?? '').trim();
            if (this.pending !== '' || question === '') {
                return;
            }
            this.pending = question;
            this.scrollDown();
            (suggested === undefined ? this.$wire.ask() : this.$wire.ask(question)).finally(() => {
                this.pending = '';
                this.scrollDown();
            });
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('askPanel', askPanel);
});
