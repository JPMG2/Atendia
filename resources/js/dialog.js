/*
 * dialog — la ÚNICA forma de avisar, advertir, confirmar o pedir un reintento.
 *
 * REGLA DE ORO: en AtendIa no existe ningún aviso nativo del navegador. Nada de
 * `alert`, `confirm` ni `prompt`: no se pueden tematizar, no respetan la
 * tipografía ni el idioma de la app, bloquean el hilo y en el panel del cliente
 * se ven como un error del sistema. Todo pasa por acá.
 * (Guía: .ai/guidelines/avisos-y-modales.md — blindada con test guardián y hook.)
 *
 * Es global y devuelve una PROMESA, así el que llama lee como código normal:
 *
 *   if (! await dialog.confirm({ title: '¿Eliminar la red?', message: '...' })) {
 *       return;
 *   }
 *
 * La ventana en sí la dibuja <livewire:dialog />, montada UNA vez en el layout
 * (igual que el toast). Abrirla no cuesta un request: es 100% Alpine.
 */

/**
 * Encola un diálogo y espera la respuesta del usuario.
 *
 * El `resolve` viaja en el detalle del evento: es la forma de que una promesa
 * cruce hasta el componente de Alpine que dibuja la ventana.
 */
function open(options) {
    return new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('dialog', { detail: { ...options, resolve } }));
    });
}

window.dialog = {
    /** Un aviso: un solo botón, no hay nada que decidir. */
    notify: (options = {}) => open({ mode: 'notify', type: 'info', ...options }),

    /** Una pregunta: cancelar o aceptar. `type: 'danger'` para lo que no se deshace. */
    confirm: (options = {}) => open({ mode: 'confirm', type: 'warning', ...options }),

    /**
     * Algo falló y se puede volver a intentar.
     *
     * Tipo `warning` y no `danger`: reintentar no destruye nada, y un botón rojo
     * ahí se lee como "esto rompe algo" justo cuando hay que animarse a tocarlo.
     */
    retry: (options = {}) => open({ mode: 'retry', type: 'warning', ...options }),
};

/**
 * El anfitrión: guarda la cola y resuelve la promesa del diálogo en pantalla.
 *
 * Hay cola porque dos avisos simultáneos son posibles (un guardado que falla
 * mientras algo más avisa) y perder el segundo es peor que hacerlo esperar.
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

            // El fondo no se scrollea detrás de la ventana: perseguir el scroll
            // de la página con un diálogo abierto es marearse.
            document.body.classList.toggle('has-dialog', this.current !== null);

            if (this.current === null) {
                return;
            }

            // El foco arranca en la acción, no en el fondo: así Enter responde y
            // el lector de pantalla anuncia de qué se trata.
            this.$nextTick(() => this.$refs.accept?.focus());
        },

        /** Rótulo de la acción: el que pidió quien abre, o el del modo. */
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

        /** Escape, click afuera y el botón de cancelar son lo mismo: que no. */
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
