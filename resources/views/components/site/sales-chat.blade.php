{{--
    Floating sales chat of the landing, phase 1: quick answers rendered right here
    from the plans table and the lang copy — no AI call, no text stored. The only
    request is a counter bump (open once per session, answer, hand-off to sales).
    Free text with the assistant is phase 2; until it exists no input is drawn.
--}}
@php
    $trialDays = (string) \App\Classes\Main\Plan::trial()->trialDays;
    $ladder = \App\Classes\Main\Plan::ladder();
    $symbol = config('atendia.billing.currency_symbol');
    $registerHref = Route::has('register') ? route('register') : '#';

    // Unset sales number = no hand-off: the "person" chip and the WhatsApp button are not drawn.
    $salesWhatsapp = \App\Models\Company::whatsapp();
    $reach = \App\Models\Company::reach();
    $nudgeAfterMs = (int) config('atendia.sales_chat.nudge_seconds') * 1000;
    $whatsappHref = $salesWhatsapp
        ? 'https://wa.me/'.$salesWhatsapp.'?text='.rawurlencode(__('landing.sales_chat.whatsapp_text'))
        : null;

    $labels = collect(__('landing.sales_chat.quick'))
        ->reject(fn (string $label, string $key): bool => $key === 'person' && $whatsappHref === null)
        ->all();
@endphp

<div
    class="sales-chat"
    x-data="{
        open: false,
        pastHero: false,
        ready: false,
        nudge: false,
        thread: [],
        labels: @js($labels),
        init() {
            // The label would cover the hero phone's chips on a desktop: icon only while the hero shows.
            const hero = document.getElementById('top');
            if (hero) new IntersectionObserver(([entry]) => (this.pastHero = ! entry.isIntersecting)).observe(hero);
            else this.pastHero = true;
            setTimeout(() => { this.ready = true; this.offerNudge(); }, @js($nudgeAfterMs));
            this.$watch('pastHero', () => this.offerNudge());
        },
        offerNudge() {
            if (! this.ready || ! this.pastHero || this.open || this.nudge) return;
            // The cookie card takes the same corner on a phone: wait until it is answered.
            const consent = document.querySelector('[data-consent]');
            if (consent && ! consent.hidden) return;
            try {
                if (localStorage.getItem('atendia-sales-nudge')) return;
                localStorage.setItem('atendia-sales-nudge', '1');
            } catch (e) { return; }
            this.nudge = true;
        },
        toggle() {
            this.open = ! this.open;
            this.nudge = false;
            if (this.open) this.count('open');
        },
        ask(key) {
            this.thread.push(key);
            this.count('answer');
            this.$nextTick(() => { this.$refs.body.scrollTop = this.$refs.body.scrollHeight; });
        },
        count(event) {
            fetch(@js(route('sales-chat.event')), {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ event }),
            }).catch(() => {});
        },
    }"
    x-on:keydown.escape.window="open = false"
    data-testid="sales-chat"
>
    <div
        class="sales-chat-panel"
        role="dialog"
        aria-label="{{ __('landing.sales_chat.title') }}"
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-200"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        data-testid="sales-chat-panel"
    >
        <div class="sales-chat-head">
            <span class="sales-chat-avatar">
                <img
                    src="{{ \App\Models\Company::versionedAsset('assets/logo-mark-color.svg') }}"
                    alt=""
                    width="24"
                    height="24"
                />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-strong font-display font-bold" style="font-size: var(--text-base)">
                    {{ __('landing.sales_chat.title') }}
                </p>
                <p class="text-muted" style="font-size: var(--text-sm)">{{ __('landing.sales_chat.subtitle') }}</p>
            </div>
            <x-ui.icon-button
                icon="x"
                variant="ghost"
                size="sm"
                :label="__('landing.sales_chat.close')"
                x-on:click="open = false"
            />
        </div>

        <div class="sales-chat-body" x-ref="body">
            <div class="pm-row in">
                <div class="pm-bubble in">{{ __('landing.sales_chat.greeting') }}</div>
            </div>

            <template x-for="(key, index) in thread" :key="index">
                <div class="flex flex-col gap-2">
                    <div class="pm-row out">
                        <div class="pm-bubble out" x-text="labels[key]"></div>
                    </div>
                    <div class="pm-row in">
                        <div class="pm-bubble in" data-testid="sales-chat-answer">
                            <template x-if="key === 'prices'">
                                <div class="flex flex-col gap-2">
                                    <span>{{ __('landing.sales_chat.prices_intro') }}</span>
                                    <ul class="flex flex-col gap-1">
                                        @foreach ($ladder as $plan)
                                            <li class="flex items-baseline justify-between gap-4">
                                                <span>{{ __('plan.names.'.$plan->code) }}</span>
                                                <span class="text-strong font-mono font-semibold">{{ $symbol.$plan->price }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <span>{{ __('landing.sales_chat.prices_outro', ['days' => $trialDays]) }}</span>
                                    <a href="#precios" class="text-brand font-semibold" x-on:click="open = false">
                                        {{ __('landing.sales_chat.prices_link') }}
                                    </a>
                                </div>
                            </template>
                            <template x-if="key === 'how'">
                                <span>{{ __('landing.sales_chat.how') }}</span>
                            </template>
                            <template x-if="key === 'trial'">
                                <div class="flex flex-col gap-2">
                                    <span>{{ __('landing.sales_chat.trial', ['days' => $trialDays]) }}</span>
                                    <a href="{{ $registerHref }}" class="text-brand font-semibold">
                                        {{ __('landing.sales_chat.trial_cta') }}
                                    </a>
                                </div>
                            </template>
                            @if ($whatsappHref)
                                <template x-if="key === 'person'">
                                    <span>{{ __('landing.sales_chat.person') }}</span>
                                </template>
                            @endif
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="sales-chat-quick">
            @foreach ($labels as $key => $label)
                <button type="button" class="sales-chat-chip" x-on:click="ask(@js($key))">{{ $label }}</button>
            @endforeach
        </div>

        @if ($whatsappHref)
            <div class="sales-chat-foot">
                <a
                    href="{{ $whatsappHref }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="sales-chat-whatsapp"
                    x-on:click="count('handoff')"
                    data-testid="sales-chat-whatsapp"
                >
                    <x-icon name="whatsapp" :size="18" />
                    {{ __('landing.sales_chat.whatsapp') }}
                </a>
                <div class="sales-chat-hours" data-testid="sales-chat-hours">
                    <x-site.opening-status :reach="$reach" />
                </div>
            </div>
        @endif
    </div>

    <div
        class="sales-chat-nudge"
        x-show="nudge && ! open"
        x-cloak
        x-transition:enter="transition duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        data-testid="sales-chat-nudge"
    >
        <button type="button" class="sales-chat-nudge-text" x-on:click="toggle()">
            {{ __('landing.sales_chat.nudge') }}
        </button>
        <x-ui.icon-button
            icon="x"
            variant="ghost"
            size="sm"
            :label="__('landing.sales_chat.nudge_dismiss')"
            x-on:click="nudge = false"
        />
    </div>

    <button
        type="button"
        class="sales-chat-fab"
        x-on:click="toggle()"
        x-bind:aria-expanded="open.toString()"
        aria-label="{{ __('landing.sales_chat.open') }}"
        data-testid="sales-chat-open"
    >
        <x-icon name="message-circle" :size="22" x-show="! open" />
        <x-icon name="x" :size="22" x-show="open" x-cloak />
        <span class="sales-chat-fab-label" x-show="! open && pastHero" x-cloak>
            {{ __('landing.sales_chat.open') }}
        </span>
    </button>
</div>
