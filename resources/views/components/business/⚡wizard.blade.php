<?php

use App\Classes\Main\AssistantPreview;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Client onboarding wizard — the parent. Children own their fields and
 * persist through their forms; this class only mirrors their state in memory
 * (by events) to drive the tabs, the checklist and the phone preview.
 */
new #[Layout('layouts::wizard')] class extends Component
{
    private const int LAST_STEP = 5;

    public int $step = 1;

    /** @var list<int> Steps completed, driving the tab ticks and the checklist. */
    public array $done = [];

    public string $businessName = '';

    public string $sector = '';

    public string $activity = '';

    /** @var list<string> */
    public array $services = [];

    /** @var list<string> */
    public array $products = [];

    public bool $connected = false;

    /**
     * The account is Breeze's register, so the wizard opens with it ticked.
     * A returning client finds the preview already speaking for their
     * business: the steps hydrate their forms, this mirrors the name.
     */
    public function mount(): void
    {
        $this->done = [1];
        $this->step = 2;
        $this->businessName = auth()->user()?->business?->name ?? '';
    }

    public function goToStep(int $step): void
    {
        $this->step = max(2, min($step, self::LAST_STEP + 1));
    }

    #[On('wizard:step-completed')]
    public function stepCompleted(int $step, bool $skipped = false, bool $connected = false): void
    {
        if (! $skipped) {
            $this->markDone($step);
        }

        if ($step === self::LAST_STEP) {
            $this->connected = $connected;
        }

        $this->goToStep($step + 1);

        $this->refreshPreview();

        $this->js('window.scrollTo({ top: 0, behavior: "smooth" })');
    }

    #[On('wizard:name-updated')]
    public function nameUpdated(string $name): void
    {
        $this->businessName = trim($name);

        $this->refreshPreview();
    }

    #[On('wizard:sector-chosen')]
    public function sectorChosen(string $sector): void
    {
        $this->sector = $sector;

        // The trade hangs off the sector: a new one voids the old choice.
        $this->activity = '';
    }

    #[On('wizard:activity-chosen')]
    public function activityChosen(string $activity): void
    {
        $this->activity = $activity;
    }

    /** @param  list<string>  $services */
    #[On('wizard:services-updated')]
    public function servicesUpdated(array $services): void
    {
        $this->services = $services;

        if ($services !== []) {
            $this->markDone(3);
        }

        $this->refreshPreview();
    }

    #[On('wizard:products-imported')]
    public function productsImported(): void
    {
        $this->markDone(4);

        $this->refreshPreview();
    }

    /** @param  list<string>  $products */
    #[On('wizard:products-updated')]
    public function productsUpdated(array $products): void
    {
        $this->products = $products;

        if ($products !== []) {
            $this->markDone(4);
        }

        $this->refreshPreview();
    }

    private function markDone(int $step): void
    {
        if (! in_array($step, $this->done, true)) {
            $this->done[] = $step;
        }
    }

    private function refreshPreview(): void
    {
        $this->dispatch('preview-updated', messages: $this->phoneMessages());
    }

    /**
     * The preview conversation, built from what the wizard knows so far by
     * the builder it shares with the dashboard simulator.
     *
     * @return list<array{type: string, who: string, html: string}>
     */
    private function phoneMessages(): array
    {
        return AssistantPreview::messages($this->businessName, $this->services, $this->products, $this->connected);
    }
};
?>

<div>
    <header class="wizard-top">
        <div class="wizard-top-in">
            <a class="wizard-wordmark" href="{{ url('/') }}">Atend<b>ia</b></a>
            <span class="wizard-crumb">{{ __('wizard.title') }}</span>
            <span class="wizard-spacer"></span>
            {{-- On screen the account stage does not count: steps 2..5 read as 1..4. --}}
            <span class="wizard-count">{!! __('wizard.progress', ['current' => '<b>'.(min($step, 5) - 1).'</b>', 'total' => '<b>4</b>']) !!}</span>
            <x-ui.theme-toggle />
            @if ($step > 1)
                <x-ui.button variant="primary" size="sm" :href="route('dashboard')">
                    {{ __('wizard.save_exit') }}
                </x-ui.button>
            @endif
        </div>
    </header>

    <div class="wizard">
        {{-- No account tab: that stage IS Breeze's register, so the bar starts
        at 02 and "your account" lives ticked in the checklist instead. --}}
        <nav class="wizard-steps" aria-label="{{ __('wizard.title') }}">
            @foreach (range(2, 5) as $n)
                <button
                    type="button"
                    wire:key="tab-{{ $n }}"
                    data-testid="wizard-tab-{{ $n }}"
                    wire:click="goToStep({{ $n }})"
                    @class(['wizard-tab', 'is-active' => $step === $n, 'is-done' => in_array($n, $done, true)])
                >
                    <span class="n">{{ sprintf('%02d', $n - 1) }}</span>{{ __('wizard.steps.'.$n.'.label') }}
                    <span class="tick"><x-icon name="check" :size="14" /></span>
                </button>
            @endforeach
        </nav>

        <div class="wizard-panel">
            {{-- Every step stays mounted (only hidden) so what was typed
            survives when navigating back and forth, mock-up style. The
            account step has no panel: it IS Breeze's register. --}}
            <div @if ($step !== 2) hidden @endif>
                <livewire:business.step-business />
            </div>
            <div @if ($step !== 3) hidden @endif>
                <livewire:business.step-services :sector="$sector" :activity="$activity" />
            </div>
            <div @if ($step !== 4) hidden @endif>
                <livewire:business.step-products />
            </div>
            <div @if ($step !== 5) hidden @endif>
                <livewire:business.step-whatsapp />
            </div>

            @if ($step > 5)
                <x-ui.card class="wizard-done">
                    <div class="wizard-seal"><x-icon name="check" :size="34" /></div>
                    <h2>{{ __('wizard.done.heading') }}</h2>
                    <p>{{ $connected ? __('wizard.done.text_connected') : __('wizard.done.text_pending') }}</p>
                    <x-ui.button variant="primary" size="lg" :href="route('dashboard')">
                        {{ __('wizard.done.cta') }}
                    </x-ui.button>
                </x-ui.card>
            @endif
        </div>

        <aside class="wizard-rail">
            <div class="wizard-preview">
                <h3>{{ __('wizard.preview.title') }}</h3>
                <p class="pdesc">{{ __('wizard.preview.description') }}</p>
                <x-client.ws-phone :name="$businessName !== '' ? $businessName : __('wizard.preview.header')" />
            </div>

            <div class="wizard-tip">
                <span class="tag">{{ __('wizard.tip_tag') }}</span>
                <p>{!! __('wizard.tips.'.min($step, 6)) !!}</p>
            </div>

            <x-ui.card class="wizard-todo-card">
                <h3>{{ __('wizard.todo.title') }}</h3>
                @foreach (range(1, 5) as $n)
                    <div wire:key="todo-{{ $n }}" @class(['wizard-todo', 'done' => in_array($n, $done, true)])>
                        <span class="box"><x-icon name="check" :size="11" /></span>
                        {{ __('wizard.todo.'.$n) }}
                        @if ($n === 4)
                            <span class="opt">({{ strtolower(__('wizard.optional')) }})</span>
                        @endif
                    </div>
                @endforeach
            </x-ui.card>
        </aside>
    </div>
</div>

@script
    <script>
        // The painting lives in ws-phone.js, shared with the dashboard
        // simulator; the wizard only forwards its live updates.
        const phone = $wire.$el.querySelector('[data-phone]');

        $wire.$on('preview-updated', ({ messages }) => wsPhone.render(phone, messages));
    </script>
@endscript
