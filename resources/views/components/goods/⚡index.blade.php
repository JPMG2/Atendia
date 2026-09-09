<?php

use Livewire\Component;

/**
 * "Tus servicios" — living mock-up, zero persistence. Two states like the
 * home: 'empty' teaches what the screen becomes and offers the trade's
 * suggestions (the catalog SUGGESTS, never forces); 'loaded' is the list the
 * assistant answers from. The switch is a mock-up affordance.
 */
new class extends Component
{
    public string $mode = 'loaded';

    /**
     * Mock rows a barber shop would load: price and duration stay optional
     * on purpose — "ofrecemos, no obligamos".
     *
     * @var list<array{name: string, description: ?string, price: ?int, minutes: ?int, active: bool}>
     */
    public array $services = [
        ['name' => 'Corte de caballero', 'description' => 'Tijera o máquina, con lavado incluido.', 'price' => 12000, 'minutes' => 30, 'active' => true],
        ['name' => 'Corte y barba', 'description' => 'El combo completo, con toalla caliente.', 'price' => 16500, 'minutes' => 45, 'active' => true],
        ['name' => 'Coloración', 'description' => null, 'price' => null, 'minutes' => 90, 'active' => true],
        ['name' => 'Peinado para eventos', 'description' => 'Se reserva con seña.', 'price' => 20000, 'minutes' => null, 'active' => false],
    ];

    /** @var list<string> */
    public array $suggestions = ['Corte de dama', 'Alisado', 'Manicuría', 'Perfilado de cejas'];

    public function switchTo(string $mode): void
    {
        $this->mode = in_array($mode, ['empty', 'loaded'], true) ? $mode : 'loaded';
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.services.title') }}</h1>
            <p class="page-head-sub">{{ __('client.services.sub') }}</p>
        </div>
        <div class="mock-switch" role="group" aria-label="{{ __('client.services.state_label') }}">
            <button type="button" wire:click="switchTo('empty')" @class(['is-active' => $mode === 'empty'])>
                {{ __('client.services.state_empty') }}
            </button>
            <button type="button" wire:click="switchTo('loaded')" @class(['is-active' => $mode === 'loaded'])>
                {{ __('client.services.state_loaded') }}
            </button>
        </div>
    </div>

    @if ($mode === 'empty')
        <x-client.offer-empty
            icon="briefcase"
            :title="__('client.services.empty_title')"
            :body="__('client.services.empty_body')"
        >
            <p class="wizard-suggest">{{ __('client.services.suggestions') }}</p>
            <div class="wizard-chips">
                @foreach ($suggestions as $suggestion)
                    <button type="button" class="wizard-chip">{{ $suggestion }}</button>
                @endforeach
            </div>
        </x-client.offer-empty>
    @else
        <x-ui.card class="p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-mono text-sm text-subtle">{{ trans_choice('client.services.count', count($services), ['count' => count($services)]) }}</p>
                <x-ui.button variant="primary" size="sm" icon="plus">{{ __('client.services.add') }}</x-ui.button>
            </div>

            {{-- Quick add mirrors the wizard step: type a name, or tap what the
            trade already suggests — same vocabulary on both screens. --}}
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <x-inputsform.input span="long" name="service_draft" maxlength="255"
                    :aria-label="__('client.services.add')" :placeholder="__('client.services.add_placeholder')" />
                <x-ui.button variant="secondary" size="sm">{{ __('client.services.add_short') }}</x-ui.button>
            </div>
            <div class="wizard-chips mt-3">
                @foreach ($suggestions as $suggestion)
                    <button type="button" class="wizard-chip">{{ $suggestion }}</button>
                @endforeach
            </div>

            <ul class="mt-4 divide-y divide-[color:var(--border-subtle)]">
                @foreach ($services as $service)
                    <li class="flex flex-wrap items-center gap-x-5 gap-y-1 rounded-lg px-2 py-2.5 transition hover:bg-sunken" wire:key="service-{{ $loop->index }}">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-strong">{{ $service['name'] }}</p>
                            @if ($service['description'] !== null)
                                <p class="text-sm text-muted">{{ $service['description'] }}</p>
                            @endif
                        </div>
                        <span @class(['font-mono text-sm', 'text-body' => $service['price'] !== null, 'text-subtle' => $service['price'] === null])>
                            {{ $service['price'] !== null ? '$ '.number_format($service['price'], 0, ',', '.') : '—' }}
                        </span>
                        <span class="w-16 text-right font-mono text-xs text-subtle">
                            {{ $service['minutes'] !== null ? $service['minutes'].' min' : '' }}
                        </span>
                        @if ($service['active'])
                            <x-ui.badge variant="brand" :dot="true">{{ __('client.services.active') }}</x-ui.badge>
                        @else
                            <span class="text-xs font-semibold text-subtle">{{ __('client.services.paused') }}</span>
                        @endif
                        <x-icon name="chevron-right" :size="16" class="text-subtle" />
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
