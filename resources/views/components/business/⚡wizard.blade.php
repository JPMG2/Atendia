<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Client onboarding wizard — the parent. Still a mock-up: children own their
 * fields and report up by events; this class mirrors that state in memory
 * (nothing persists) to drive the tabs, the checklist and the phone preview.
 */
new #[Title('Alta de cliente')] #[Layout('layouts::wizard')] class extends Component {
    private const int LAST_STEP = 5;

    public int $step = 1;

    /** @var list<int> Steps completed, driving the tab ticks and the checklist. */
    public array $done = [];

    public string $businessName = '';

    public string $sector = '';

    /** @var list<string> */
    public array $services = [];

    public bool $productsLoaded = false;

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
        $this->productsLoaded = true;

        $this->markDone(4);

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
     * The preview conversation, built from what the wizard knows so far.
     * Values typed by the user are escaped HERE: the phone paints raw HTML.
     *
     * @return list<array{type: string, who: string, html: string}>
     */
    private function phoneMessages(): array
    {
        if ($this->businessName === '') {
            return [];
        }

        $business = e($this->businessName);

        $messages = [
            ['type' => 'in', 'who' => __('wizard.phone.client'), 'html' => __('wizard.phone.q_open')],
            [
                'type' => 'out',
                'who' => __('wizard.phone.assistant_of', ['business' => $business]),
                'html' => __('wizard.phone.a_open', ['business' => $business]),
            ],
        ];

        if ($this->services !== []) {
            $last = e(mb_strtolower(end($this->services)));
            $list = '<b>'.implode('</b>, <b>', array_map(e(...), $this->services)).'</b>';

            $messages[] = ['type' => 'in', 'who' => __('wizard.phone.client'), 'html' => __('wizard.phone.q_service', ['service' => $last])];
            $messages[] = ['type' => 'out', 'who' => __('wizard.phone.assistant'), 'html' => __('wizard.phone.a_service', ['business' => $business, 'services' => $list])];
        }

        if ($this->productsLoaded) {
            $messages[] = ['type' => 'in', 'who' => __('wizard.phone.client'), 'html' => __('wizard.phone.q_product')];
            $messages[] = ['type' => 'out', 'who' => __('wizard.phone.assistant'), 'html' => __('wizard.phone.a_product')];
        }

        if ($this->connected) {
            $messages[] = ['type' => 'out', 'who' => __('wizard.phone.assistant'), 'html' => __('wizard.phone.connected')];
        }

        return $messages;
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
                <button type="button" wire:key="tab-{{ $n }}" data-testid="wizard-tab-{{ $n }}"
                        wire:click="goToStep({{ $n }})"
                        @class(['wizard-tab', 'is-active' => $step === $n, 'is-done' => in_array($n, $done, true)])>
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
                <livewire:business.step-services :sector="$sector" />
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
                {{-- wire:ignore: the conversation is painted by the script
                below and a morph would wipe it. --}}
                <div class="wizard-phone" data-phone wire:ignore data-empty="{{ __('wizard.preview.empty') }}">
                    <div class="wizard-phone-empty">{{ __('wizard.preview.empty') }}</div>
                </div>
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
    // Port of the static mock-up's preview: the assistant "types" before each
    // reply. That one-second wait IS the demo.
    const phone = $wire.$el.querySelector('[data-phone]');
    const emptyHtml = () => `<div class="wizard-phone-empty">${phone.dataset.empty}</div>`;

    let painted = 0;
    let token = 0;

    const bubble = (m) => `<div class="msg ${m.type}"><span class="who">${m.who}</span>${m.html}</div>`;

    $wire.$on('preview-updated', ({ messages }) => render(messages));

    function render(messages) {
        // Any repaint invalidates pending animation timers: without this, the
        // bubbles scheduled for the FIRST keystroke fire after the silent
        // repaint of the full name and the reply shows up twice.
        if (messages.length === 0) {
            token++;
            phone.innerHTML = emptyHtml();
            painted = 0;

            return;
        }

        // While the name is being typed nothing re-animates: text only.
        if (messages.length === painted) {
            token++;
            phone.innerHTML = messages.map(bubble).join('');

            return;
        }

        const current = ++token;
        painted = messages.length;
        phone.innerHTML = '';

        let delay = 0;

        messages.forEach((m) => {
            if (m.type === 'out') {
                const typingAt = delay;
                setTimeout(() => {
                    if (current !== token) return;
                    phone.insertAdjacentHTML('beforeend', '<div class="typing" data-typing><i></i><i></i><i></i></div>');
                    phone.scrollTop = phone.scrollHeight;
                }, typingAt);
                delay += 900;
            }

            setTimeout(() => {
                if (current !== token) return;
                phone.querySelector('[data-typing]')?.remove();
                phone.insertAdjacentHTML('beforeend', bubble(m));
                phone.scrollTop = phone.scrollHeight;
            }, delay);
            delay += 450;
        });
    }
</script>
@endscript
