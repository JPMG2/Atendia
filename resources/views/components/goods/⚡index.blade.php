<?php

use App\Models\BusinessActivity;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
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

    /**
     * The trade's suggested names — the same vocabulary the wizard chips
     * speak, straight from the signed-in business's primary activity. No
     * activity yet simply means no chips; never a hardcoded list.
     *
     * @return list<string>
     */
    #[Computed]
    public function suggestions(): array
    {
        $activity = Auth::user()?->business?->primaryActivity();

        return $activity === null ? [] : BusinessActivity::suggestionsName(code: $activity->code);
    }

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
            @if ($this->suggestions !== [])
                <p class="wizard-suggest">{{ __('client.services.suggestions') }}</p>
                <div class="wizard-chips">
                    @foreach ($this->suggestions as $suggestion)
                        <button type="button" class="wizard-chip">{{ $suggestion }}</button>
                    @endforeach
                </div>
            @endif
        </x-client.offer-empty>
    @else
        {{-- The assistant offers to write; it never blocks the manual path. --}}
        <x-ui.ai-banner
            class="mb-3"
            :title="__('client.services.ai_title')"
            :body="__('client.services.ai_body')"
            :action="__('client.services.ai_action')"
        />

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
            @if ($this->suggestions !== [])
                <div class="wizard-chips mt-3">
                    @foreach ($this->suggestions as $suggestion)
                        <button type="button" class="wizard-chip">{{ $suggestion }}</button>
                    @endforeach
                </div>
            @endif

            {{-- The state rides next to the name and the actions sit on the
            row: pausing or editing never costs opening anything. --}}
            <ul class="mt-4 divide-y divide-[color:var(--border-subtle)]">
                @foreach ($services as $service)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg px-2 py-2.5 transition hover:bg-sunken" wire:key="service-{{ $loop->index }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-strong">{{ $service['name'] }}</p>
                                @if ($service['active'])
                                    <x-ui.badge variant="brand" :dot="true">{{ __('client.services.active') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral">{{ __('client.services.paused') }}</x-ui.badge>
                                @endif
                            </div>
                            @if ($service['description'] !== null)
                                <p class="text-sm text-muted">{{ $service['description'] }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p @class(['font-mono', 'text-base font-bold text-strong' => $service['price'] !== null, 'text-sm text-subtle' => $service['price'] === null])>
                                {{ $service['price'] !== null ? '$ '.number_format($service['price'], 0, ',', '.') : '—' }}
                            </p>
                            @if ($service['minutes'] !== null)
                                <p class="flex items-center justify-end gap-1 font-mono text-xs text-subtle">
                                    <x-icon name="clock" :size="12" />{{ $service['minutes'] }} min
                                </p>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            <x-ui.icon-button icon="pencil" size="sm" variant="ghost"
                                :label="__('client.services.edit', ['name' => $service['name']])" />
                            <x-ui.icon-button :icon="$service['active'] ? 'pause' : 'play'" size="sm" variant="ghost"
                                :label="__($service['active'] ? 'client.services.pause' : 'client.services.resume', ['name' => $service['name']])" />
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
