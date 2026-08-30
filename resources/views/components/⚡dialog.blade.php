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
        {{-- Cerrar al tocar el fondo va acá con `.self` (solo si el click fue en el
             fondo mismo), NO con un `.outside` sobre la ventana: `.outside`
             engancha su listener en el documento DURANTE el mismo click que abre
             el diálogo, ese click sigue burbujeando y lo cierra en el acto — se
             abría y se cerraba dentro del mismo evento. --}}
        <div class="dialog-backdrop" x-transition.opacity x-on:keydown.escape.window="cancel()"
            x-on:click.self="cancel()">

            {{-- `alertdialog` y no `dialog`: interrumpe para pedir una respuesta,
                 así el lector de pantalla lo anuncia entero al abrirse. --}}
            <div class="dialog" role="alertdialog" aria-modal="true" aria-labelledby="dialog-title"
                aria-describedby="dialog-message" x-transition
                x-transition:enter-start="dialog-off" x-transition:leave-end="dialog-off">

                {{-- El disco teñido dice de qué se trata antes de leer una palabra.
                     El color sale del tipo; el glifo, del registro de iconos. --}}
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

                {{-- Cancelar primero y la acción a la derecha, igual que el pie de
                     los formularios: el botón que confirma siempre cae en el mismo
                     lugar de la pantalla. Un aviso no lleva cancelar: no hay nada
                     que decidir. --}}
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
