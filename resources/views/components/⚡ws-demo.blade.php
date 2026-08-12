<?php

use App\Events\MessageSent;
use App\Models\Business;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Prueba de vida del WebSocket sobre un canal PRIVADO por negocio.
 * Descartable: cuando exista el chat real, esto se borra.
 */
new class extends Component {
    /** @var array<int, string> */
    public array $messages = [];

    public string $draft = '';

    /** Negocio cuyo canal se escucha. El dueño no tiene, así que toma el primero. */
    #[Locked]
    public ?int $businessId = null;

    public function mount(): void
    {
        $this->businessId = auth()->user()?->business_id ?? Business::query()->value('id');
    }

    public function send(): void
    {
        if (trim($this->draft) === '' || $this->businessId === null) {
            return;
        }

        MessageSent::dispatch($this->draft, $this->businessId);

        $this->draft = '';
    }

    /**
     * El canal lleva el id en el nombre, así que el listener se arma en runtime.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        if ($this->businessId === null) {
            return [];
        }

        return ["echo-private:business.{$this->businessId},.message.sent" => 'onMessage'];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function onMessage(array $event): void
    {
        $this->messages[] = (string) $event['body'];
    }

    #[Computed]
    public function business(): ?Business
    {
        return $this->businessId === null ? null : Business::query()->find($this->businessId);
    }
};
?>

<div class="card" style="padding:20px; max-width:520px">
    <h2 class="page-head-title">Prueba de WebSocket</h2>

    @if ($businessId === null)
        <x-ui.alert>No hay ningún negocio cargado, así que no hay canal privado que escuchar.</x-ui.alert>
    @else
        <p class="page-head-sub">
            Canal privado del negocio <b class="mono">{{ $this->business?->name }}</b>.
            Abrí esta pantalla en dos pestañas: lo que escribas en una aparece en la otra sin recargar.
        </p>

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
    @endif
</div>
