<?php

use Livewire\Component;

/**
 * La ventana de avisos del sistema, REUTILIZABLE por cualquier componente.
 *
 * No tiene estado en el servidor: se monta una sola vez en el layout —igual que
 * el toast— y escucha el evento `dialog` que dispara la función global
 * `dialog.*` (resources/js/dialog.js). Abrir un aviso NO cuesta un request.
 *
 * REGLA DE ORO: acá pasan TODOS los avisos del sistema. En AtendIa no existe un
 * `alert`, `confirm` ni `prompt` del navegador — ni en el panel admin ni
 * en el del cliente. Ver .ai/guidelines/avisos-y-modales.md.
 *
 * Uso desde cualquier componente Alpine o Livewire:
 *   if (! await dialog.confirm({ title: '…', message: '…', type: 'danger' })) return;
 */
new class extends Component {};
?>

<div x-data="dialogHost({ labels: @js([
    'accept' => __('dialog.accept'),
    'cancel' => __('dialog.cancel'),
    'understood' => __('dialog.understood'),
    'retry' => __('dialog.retry'),
]) })" x-on:dialog.window="push($event.detail)">

    <template x-if="current !== null">
        {{-- Closing on a backdrop click uses `.self` and NOT `.outside` on the
        window: `.outside` hooks its listener DURING the very click that
        opens the dialog, and that click keeps bubbling and closes it on
        the spot. --}}
        <div class="dialog-backdrop" x-transition.opacity x-on:keydown.escape.window="cancel()"
            x-on:click.self="cancel()">

            {{-- `alertdialog` and not `dialog`: it interrupts to ask for an
            answer, so the screen reader announces it whole. --}}
            <div class="dialog" role="alertdialog" aria-modal="true" aria-labelledby="dialog-title"
                aria-describedby="dialog-message" x-transition
                x-transition:enter-start="dialog-off" x-transition:leave-end="dialog-off">

                {{-- The tinted disc says what this is about before a word is read.
                The colour comes from the type, the glyph from the icon
                registry. --}}
                <span class="dialog-icon" x-bind:class="'dialog-icon-' + current.type" aria-hidden="true">
                    <template x-if="current.type === 'info'"><x-icon name="info" :size="22" /></template>
                    <template x-if="current.type === 'success'"><x-icon name="circle-check" :size="22" /></template>
                    <template x-if="current.type === 'warning'"><x-icon name="triangle-alert" :size="22" /></template>
                    <template x-if="current.type === 'danger'"><x-icon name="triangle-alert" :size="22" /></template>
                </span>

                <div class="dialog-body">
                    <h2 class="dialog-title" id="dialog-title" x-text="current.title"></h2>
                    <p class="dialog-message" id="dialog-message" x-text="current.message" x-show="current.message">
                    </p>
                </div>

                {{-- Cancel first and the action on the right, same as a form's
                footer: the confirming button always lands in the same
                place. A notice carries no cancel — there is nothing to
                decide. --}}
                <div class="dialog-foot">
                    <x-ui.button variant="ghost" x-show="current.mode !== 'notify'" x-on:click="cancel()">
                        <span x-text="cancelLabel()"></span>
                    </x-ui.button>

                    <x-ui.button variant="primary" x-ref="accept" x-on:click="accept()"
                        x-bind:class="current.type === 'danger' ? 'btn-danger' : ''">
                        <span x-text="acceptLabel()"></span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </template>
</div>
