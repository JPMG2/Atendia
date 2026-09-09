<?php

use App\Classes\Main\Client;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Mi negocio" — the client's business profile. The parent only
 * orchestrates: each card is its own child component with its own save
 * (offer-never-require: you save the piece you touched). With a section slug
 * it renders that single card — the deep links the menu and the meter use.
 */
new class extends Component
{
    public ?string $section = null;

    /**
     * The real meter: read from the client's main class, the only place that
     * sees every piece. At 100% it turns into the LinkedIn-style celebration.
     *
     * @return array{done: int, total: int, missing: list<string>}
     */
    #[Computed]
    public function strength(): array
    {
        return Client::for(Auth::user())->profileStrength();
    }

    /**
     * Piece → the section card that completes it, for the meter deep links.
     *
     * @return array<string, string>
     */
    public function meterSections(): array
    {
        return [
            'personal_data' => 'contacto',
            'tax_details' => 'facturacion',
            'schedule' => 'horarios',
            'social_media' => 'redes',
        ];
    }

    /**
     * Slug → child component. Only route-baked slugs ever arrive, so an
     * unknown key simply falls back to the full page.
     *
     * @return array<string, string>
     */
    public function sections(): array
    {
        return [
            'identidad' => 'client.section-identity',
            'ubicacion' => 'client.section-location',
            'horarios' => 'client.section-hours',
            'contacto' => 'client.section-contact',
            'redes' => 'client.section-social',
            'facturacion' => 'client.section-billing',
        ];
    }
};
?>

<div x-data="{ tryOpen: false }">
    <div class="page-head">
        <div class="bp-head">
            {{-- From a single section the arrow returns to the full profile; from there, home. --}}
            <a
                href="{{ $section ? route('my-business') : route('dashboard') }}"
                wire:navigate
                class="bp-back"
                aria-label="{{ __('client.business.back') }}"
            >
                <x-icon name="chevron-left" :size="18" />
            </a>
            <div>
                <h1 class="page-head-title">{{ __('client.business.title') }}</h1>
                <p class="page-head-sub">{{ __('client.business.sub') }}</p>
            </div>
        </div>
    </div>

    <div class="bp-layout">
        <div class="bp-main">
            @if ($section !== null && isset($this->sections()[$section]))
                @livewire($this->sections()[$section])
            @else
                @foreach ($this->sections() as $slug => $component)
                    @livewire($component, [], key($slug))
                @endforeach
            @endif
        </div>

        <aside class="bp-rail">
            {{-- Value before commitment: the simulated chat answers with
            whatever the profile holds right now, no WhatsApp needed. --}}
            <x-ui.button variant="primary" icon="message-circle" :fullWidth="true" x-on:click="tryOpen = true">
                {{ __('client.business.try.button') }}
            </x-ui.button>

            @php
                $strength = $this->strength;
                $percent = (int) round($strength['done'] / max(1, $strength['total']) * 100);
            @endphp
            @if ($percent === 100)
                <x-ui.card class="bp-card bp-complete">
                    <span class="bp-complete-seal"><x-icon name="circle-check" :size="26" /></span>
                    <b>{{ __('client.business.meter.complete_title') }}</b>
                    <p>{{ __('client.business.meter.complete_sub') }}</p>
                </x-ui.card>
            @else
                <x-ui.card class="bp-card">
                    <div class="bp-meter-head">
                        <b>{{ __('client.business.meter.title', ['percent' => $percent]) }}</b>
                        <span class="font-mono">{{ __('client.business.meter.count', ['done' => $strength['done'], 'total' => $strength['total']]) }}</span>
                    </div>
                    <div
                        class="setup-bar"
                        role="progressbar"
                        aria-valuenow="{{ $percent }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <i style="width: {{ $percent }}%"></i>
                    </div>
                    {{-- Each pending piece deep-links straight to its section. --}}
                    @foreach ($this->meterSections() as $piece => $slug)
                        @if (in_array($piece, $strength['missing'], true))
                            <a href="{{ route('my-business.'.$slug) }}" wire:navigate class="bp-todo"
                                ><em></em>{{ __('client.business.meter.'.$piece) }}</a>
                        @else
                            <div class="bp-todo is-done"><em></em>{{ __('client.business.meter.'.$piece) }}</div>
                        @endif
                    @endforeach
                </x-ui.card>
            @endif

            <x-ui.card class="bp-card">
                <div class="bp-card-head">
                    <h2>{{ __('client.business.preview.title') }}</h2>
                </div>
                <p class="bp-card-sub">{{ __('client.business.preview.sub') }}</p>
                <div class="bp-chat">
                    <div class="bp-msg">
                        <div class="bp-msg-who">
                            <x-ui.avatar :name="__('client.business.mock.name')" size="xs" />
                            <b data-bp-preview="name">{{ __('client.business.mock.name') }}</b>
                        </div>
                        <span data-bp-preview="message">{{ __('client.business.preview.message', ['name' => __('client.business.mock.name'), 'description' => __('client.business.mock.description')]) }}</span>
                    </div>
                    {{-- The assistant thanks each saved section from here. --}}
                    <div class="bp-msg bp-msg-thanks" data-bp-preview="thanks" hidden></div>
                    <p class="bp-hint">{{ __('client.business.preview.caption') }}</p>
                </div>
            </x-ui.card>
        </aside>
    </div>

    {{-- Escape and the backdrop close without acting, like every overlay here. --}}
    <div
        class="bp-try-overlay"
        x-cloak
        x-show="tryOpen"
        x-transition.opacity
        x-on:keydown.escape.window="tryOpen = false"
        x-on:click.self="tryOpen = false"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('client.business.try.title') }}"
    >
        <div class="bp-try-panel">
            <div class="bp-try-head">
                <h2>{{ __('client.business.try.title') }}</h2>
                <x-ui.icon-button
                    icon="x"
                    size="sm"
                    variant="ghost"
                    :label="__('client.business.try.close')"
                    x-on:click="tryOpen = false"
                />
            </div>
            <div class="wizard-phone-frame">
                <div class="wizard-phone-screen">
                    <div class="wizard-phone-top">
                        <span class="pavatar"><x-icon name="bot" :size="18" /></span>
                        <span class="pmeta">
                            <span class="pname">{{ __('client.business.mock.name') }}</span>
                            <span class="pstatus"><i></i>{{ __('client.business.try.online') }}</span>
                        </span>
                    </div>
                    <div class="wizard-phone">
                        <div class="msg in">{{ __('client.business.try.q1') }}</div>
                        <div class="msg out">{{ __('client.business.try.a1') }}</div>
                        <div class="msg in">{{ __('client.business.try.q2') }}</div>
                        <div class="msg out">{{ __('client.business.try.a2') }}</div>
                    </div>
                </div>
            </div>
            <p class="bp-hint">{{ __('client.business.try.hint') }}</p>
        </div>
    </div>

    @script
        <script>
            // Shopify-style unsaved guard: typing in a card arms it, that card's
            // save disarms it, and leaving asks through the system dialog. State
            // lives on window so the once-bound navigate listener survives SPA
            // visits without stacking copies of itself.
            window.bpUnsaved = false;

            // The rail preview is ALIVE: identity fields repaint the bubble on
            // every keystroke, and a saved section earns a thank-you message —
            // filling the profile reads as teaching, not form-filling.
            const template = @js(__('client.business.preview.message'));
            const thanksCopy = @js(__('client.business.preview.thanks'));
            const fallbackName = @js(__('client.business.mock.name'));
            const fallbackDescription = @js(__('client.business.mock.description'));

            const nameEl = $wire.$el.querySelector('[data-bp-preview="name"]');
            const messageEl = $wire.$el.querySelector('[data-bp-preview="message"]');
            const thanksEl = $wire.$el.querySelector('[data-bp-preview="thanks"]');
            let previewName = fallbackName;
            let previewDescription = fallbackDescription;
            let thanksTimer = null;

            const paintPreview = () => {
                if (!nameEl || !messageEl) return;
                nameEl.textContent = previewName;
                messageEl.textContent = template.replace(':name', previewName).replace(':description', previewDescription);
            };

            $wire.$el.addEventListener('input', (event) => {
                if (!event.target.closest('.bp-card')) return;

                window.bpUnsaved = true;

                const field = event.target.getAttribute('name');
                if (field === 'name') {
                    previewName = event.target.value.trim() || fallbackName;
                    paintPreview();
                } else if (field === 'description') {
                    previewDescription = event.target.value.trim() || fallbackDescription;
                    paintPreview();
                }
            });

            $wire.$el.addEventListener('click', (event) => {
                const actions = event.target.closest('.bp-card-actions');
                if (!actions) return;

                window.bpUnsaved = false;

                const section = actions.closest('[data-section]')?.dataset.section;
                if (thanksEl && thanksCopy[section]) {
                    thanksEl.textContent = thanksCopy[section];
                    thanksEl.hidden = false;
                    clearTimeout(thanksTimer);
                    thanksTimer = setTimeout(() => {
                        thanksEl.hidden = true;
                    }, 4000);
                }
            });

            if (!window.bpGuardBound) {
                window.bpGuardBound = true;

                document.addEventListener('livewire:navigate', (event) => {
                    if (!window.bpUnsaved) {
                        return;
                    }

                    event.preventDefault();
                    const url = event.detail.url.href;

                    dialog
                        .confirm({
                            title: @js(__('client.business.unsaved.title')),
                            message: @js(__('client.business.unsaved.message')),
                            accept: @js(__('client.business.unsaved.accept')),
                            type: 'warning',
                        })
                        .then((leave) => {
                            if (leave) {
                                window.bpUnsaved = false;
                                Livewire.navigate(url);
                            }
                        });
                });

                // Arriving anywhere means the pending navigation was allowed.
                document.addEventListener('livewire:navigated', () => {
                    window.bpUnsaved = false;
                });
            }
        </script>
    @endscript
</div>
