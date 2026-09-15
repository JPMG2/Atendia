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

    /** Open sheet's row index; the mock opens one so the screen shows it. */
    public ?int $editing = 1;

    /**
     * Mock rows a barber shop would load: price and duration stay optional
     * on purpose — "ofrecemos, no obligamos". `prep` is what the client must
     * know or bring; `deposit` is the booking down payment.
     *
     * @var list<array{name: string, description: ?string, price: ?int, minutes: ?int, deposit: ?int, prep: ?string, active: bool}>
     */
    public array $services = [
        ['name' => 'Corte de caballero', 'description' => 'Tijera o máquina, con lavado incluido.', 'price' => 12000, 'minutes' => 30, 'deposit' => null, 'prep' => null, 'active' => true],
        ['name' => 'Corte y barba', 'description' => 'El combo completo, con toalla caliente.', 'price' => 16500, 'minutes' => 45, 'deposit' => 5000, 'prep' => 'Llega con el pelo seco; la toalla caliente hace el resto.', 'active' => true],
        ['name' => 'Coloración', 'description' => null, 'price' => null, 'minutes' => 90, 'deposit' => null, 'prep' => 'No laves tu pelo el día anterior.', 'active' => true],
        ['name' => 'Peinado para eventos', 'description' => 'Se reserva con seña.', 'price' => 20000, 'minutes' => null, 'deposit' => 8000, 'prep' => null, 'active' => false],
    ];

    /** How many rows carry a price — what the completeness meter reads. */
    #[Computed]
    public function priced(): int
    {
        return count(array_filter($this->services, fn (array $service): bool => $service['price'] !== null));
    }

    public function edit(int $index): void
    {
        $this->editing = $this->editing === $index ? null : $index;
    }

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

        {{-- Per-row completeness, GBP-style: it nudges, it never blocks. --}}
        <div class="card mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2.5">
            <p class="text-sm font-bold text-strong">{{ __('client.services.meter', ['done' => $this->priced, 'total' => count($services)]) }}</p>
            <div class="h-1.5 min-w-24 flex-1 overflow-hidden rounded-full bg-sunken">
                <div class="h-full rounded-full bg-[color:var(--brand)]" style="width: {{ count($services) > 0 ? round($this->priced / count($services) * 100) : 0 }}%"></div>
            </div>
            <p class="text-sm text-muted">{{ __('client.services.meter_hint') }}</p>
        </div>

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
                            @if ($service['price'] !== null)
                                <p class="font-mono text-base font-bold text-strong">$ {{ number_format($service['price'], 0, ',', '.') }}</p>
                            @else
                                <span class="rounded-full bg-[color:var(--warning-soft)] px-2 py-0.5 text-xs font-semibold text-[color:var(--warning)]">
                                    {{ __('client.services.no_price') }}
                                </span>
                            @endif
                            <p class="flex items-center justify-end gap-2 font-mono text-xs text-subtle">
                                @if ($service['minutes'] !== null)
                                    <span class="flex items-center gap-1"><x-icon name="clock" :size="12" />{{ $service['minutes'] }} min</span>
                                @endif
                                @if ($service['deposit'] !== null)
                                    <span>{{ __('client.services.deposit_short', ['amount' => number_format($service['deposit'], 0, ',', '.')]) }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <x-ui.icon-button icon="pencil" size="sm" variant="ghost" wire:click="edit({{ $loop->index }})"
                                :label="__('client.services.edit', ['name' => $service['name']])" />
                            <x-ui.icon-button :icon="$service['active'] ? 'pause' : 'play'" size="sm" variant="ghost"
                                :label="__($service['active'] ? 'client.services.pause' : 'client.services.resume', ['name' => $service['name']])" />
                        </div>
                    </li>

                    {{-- The sheet opens UNDER its row: cost, prep and deposit
                    live on the tag itself, never on a separate screen. --}}
                    @if ($editing === $loop->index)
                        <li class="py-3" wire:key="service-sheet-{{ $loop->index }}">
                            <div class="rounded-xl bg-sunken p-5">
                                <h2 class="font-display text-base font-bold text-strong">{{ __('client.services.sheet_title', ['name' => $service['name']]) }}</h2>
                                <p class="mb-4 text-sm text-muted">{{ __('client.services.sheet_hint') }}</p>

                                <div class="flex flex-wrap items-start gap-3">
                                    <x-inputsform.input span="long" name="sheet_name" :label="__('client.services.field_name')"
                                        :value="$service['name']" />
                                    <x-inputsform.input span="code" name="sheet_minutes" :label="__('client.services.field_minutes')"
                                        :value="$service['minutes']" class="font-mono" inputmode="numeric" />
                                    <div class="min-w-36 flex-1">
                                        <x-ui.select name="sheet_price_type" :label="__('client.services.field_price_type')" :options="[
                                            'fixed' => __('client.services.price_fixed'),
                                            'from' => __('client.services.price_from'),
                                            'free' => __('client.services.price_free'),
                                            'talk' => __('client.services.price_talk'),
                                        ]" />
                                    </div>
                                    <x-inputsform.input span="code" name="sheet_price" :label="__('client.services.field_amount')"
                                        :value="$service['price'] !== null ? number_format($service['price'], 0, ',', '.') : ''" class="font-mono" inputmode="numeric" />
                                    <x-inputsform.input span="short" name="sheet_deposit" :label="__('client.services.field_deposit')"
                                        :hint="__('client.services.deposit_help')"
                                        :value="$service['deposit'] !== null ? number_format($service['deposit'], 0, ',', '.') : ''" class="font-mono" inputmode="numeric" />
                                </div>

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <x-ui.textarea name="sheet_description" :rows="2" :label="__('client.services.field_description')"
                                        :hint="__('client.services.description_help')">{{ $service['description'] }}</x-ui.textarea>
                                    <x-ui.textarea name="sheet_prep" :rows="2" :label="__('client.services.field_prep')"
                                        :hint="__('client.services.prep_help')">{{ $service['prep'] }}</x-ui.textarea>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <x-ui.switch name="sheet_active" :label="__('client.services.offered')" :checked="$service['active']" />
                                    <span class="flex-1"></span>
                                    <x-ui.button variant="ghost" size="sm" wire:click="edit({{ $loop->index }})">{{ __('client.services.sheet_cancel') }}</x-ui.button>
                                    <x-ui.button variant="primary" size="sm" wire:click="edit({{ $loop->index }})">{{ __('client.services.sheet_save') }}</x-ui.button>
                                </div>
                            </div>
                        </li>
                    @endif
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
