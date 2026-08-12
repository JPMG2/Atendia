<?php

use App\Events\MessageSent;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Prueba de vida del WebSocket. Descartable: cuando el chat real exista, esto se borra.
 *
 * `send()` dispara el evento; NADIE le avisa a este componente. El mensaje llega
 * por el socket, y por eso aparece también en cualquier otra pestaña abierta.
 */
new class extends Component {
    /** @var array<int, string> */
    public array $messages = [];

    public string $draft = '';

    public function send(): void
    {
        if (trim($this->draft) === '') {
            return;
        }

        MessageSent::dispatch($this->draft);

        $this->draft = '';
    }

    #[On('echo:demo,.message.sent')]
    public function onMessage(array $event): void
    {
        $this->messages[] = $event['body'];
    }
};
?>

<div class="card" style="padding:20px; max-width:520px">
    <h2 class="page-head-title">Prueba de WebSocket</h2>
    <p class="page-head-sub">Abrí esta pantalla en dos pestañas: lo que escribas en una aparece en la otra sin recargar.</p>

    <div style="display:flex; gap:8px; align-items:flex-end; margin:16px 0">
        <x-inputsform.input name="draft" label="Mensaje" wire:model="draft" wire:keydown.enter="send" />
        <x-ui.button variant="primary" icon="send" wire:click="send">Enviar</x-ui.button>
    </div>

    <ul style="display:flex; flex-direction:column; gap:6px">
        @forelse ($messages as $message)
            <li class="catalog-code">{{ $message }}</li>
        @empty
            <li class="field-hint">Todavía no llegó nada.</li>
        @endforelse
    </ul>
</div>
