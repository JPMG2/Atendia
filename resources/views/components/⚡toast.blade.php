<?php

use Livewire\Component;

/**
 * Global toast, REUSABLE by any component.
 *
 * No server state: mounted once in the layout and listening for the `notify`
 * event that `HasNotifications` fires out of a `NotificationDto`. Being all
 * Alpine, showing one costs no extra request.
 *
 *   $this->dispatchNotification($this->form->storeCurrency());
 */
new class extends Component {};
?>

<div class="toast-stack" x-data="toastStack()" x-on:notify.window="push($event.detail)" role="status"
    aria-live="polite" aria-atomic="false">
    <template x-for="toast in toasts" :key="toast.id">
        <div class="toast" x-bind:class="'toast-' + toast.type" x-show="toast.visible" x-cloak
            x-transition:enter="toast-trans" x-transition:enter-start="toast-off" x-transition:enter-end="toast-on"
            x-transition:leave="toast-trans" x-transition:leave-start="toast-on" x-transition:leave-end="toast-off">

            <span class="toast-icon">
                <template x-if="toast.type === 'success'"><x-icon name="circle-check" :size="18" /></template>
                <template x-if="toast.type === 'error'"><x-icon name="circle-x" :size="18" /></template>
                <template x-if="toast.type === 'warning'"><x-icon name="triangle-alert" :size="18" /></template>
                <template x-if="toast.type === 'info'"><x-icon name="info" :size="18" /></template>
            </span>

            <p class="toast-message" x-text="toast.message"></p>

            <button type="button" class="toast-close" aria-label="{{ __('toast.dismiss') }}"
                x-on:click="dismiss(toast.id)">
                <x-icon name="x" :size="16" />
            </button>
        </div>
    </template>
</div>

@script
    <script>
        Alpine.data('toastStack', () => ({
            toasts: [],
            lastId: 0,

            // Tipos válidos del NotificationType; cualquier otro cae a 'info'.
            types: ['success', 'error', 'warning', 'info'],

            // Cuánto vive el aviso y cuánto dura la transición de salida (ver .toast-trans).
            duration: 5000,
            leaveDuration: 260,

            push(detail) {
                const message = detail?.message ?? '';

                if (!message) return;

                const id = ++this.lastId;

                this.toasts.push({
                    id,
                    message,
                    type: this.types.includes(detail?.type) ? detail.type : 'info',
                    visible: false,
                });

                // Se pinta oculto y se muestra en el siguiente tick: así x-show
                // dispara la transición de entrada también en el primer render.
                this.$nextTick(() => {
                    const toast = this.find(id);
                    if (toast) toast.visible = true;
                });

                setTimeout(() => this.dismiss(id), this.duration);
            },

            dismiss(id) {
                const toast = this.find(id);

                if (!toast || !toast.visible) return;

                toast.visible = false;

                // Se saca del array recién cuando terminó la transición de salida.
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, this.leaveDuration);
            },

            find(id) {
                return this.toasts.find(t => t.id === id);
            },
        }));
    </script>
@endscript
