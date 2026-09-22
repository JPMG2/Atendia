@php
    $bubbles = [
        ['side' => 'in',  'time' => '09:41', 'text' => __('landing.phone.b1')],
        ['side' => 'out', 'time' => '09:41', 'text' => __('landing.phone.b2')],
        ['side' => 'in',  'time' => '09:42', 'text' => __('landing.phone.b3')],
        ['side' => 'out', 'time' => '09:42', 'text' => __('landing.phone.b4')],
    ];

    // Extra exchanges hero.js keeps appending so the demo chat never looks
    // dead; their timestamps come from the visitor's real clock.
    $livePool = [
        ['side' => 'in',  'text' => __('landing.phone.b5')],
        ['side' => 'out', 'text' => __('landing.phone.b6')],
        ['side' => 'in',  'text' => __('landing.phone.b7')],
        ['side' => 'out', 'text' => __('landing.phone.b8')],
        ['side' => 'in',  'text' => __('landing.phone.b9')],
        ['side' => 'out', 'text' => __('landing.phone.b10')],
    ];
@endphp

<div class="hero-phone-enter relative">
    {{-- The rubro selector: "cualquier rubro" demonstrated, not claimed.
    Switching hands the phone to that rubro's REAL demo business. --}}
    <div class="relative mb-3 flex flex-col items-center gap-1.5" style="z-index: 1">
        <p class="text-subtle" style="font-size: 11px">{{ __('landing.demo.rubro_label') }}</p>
        {{-- Only 3 pills show at a time — the row keeps its width and hero.js
        rotates the rest through like a carousel until the visitor takes over. --}}
        <div
            class="flex gap-1.5"
            role="group"
            data-demo-rubro-track
            style="transition: opacity 0.25s"
            aria-label="{{ __('landing.demo.rubro_label') }}"
        >
            @foreach (__('landing.demo.rubros') as $slug => $rubro)
                <button
                    type="button"
                    data-demo-rubro="{{ $slug }}"
                    data-demo-name="{{ $rubro['name'] }}"
                    data-demo-header-text="{{ __('landing.demo.header', ['name' => $rubro['name']]) }}"
                    data-demo-greeting="{{ __('landing.demo.greeting', ['name' => $rubro['name']]) }}"
                    data-demo-cta-label="{{ __('landing.demo.cta_rubro', ['rubro' => $rubro['noun']]) }}"
                    aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full font-semibold transition-colors',
                        'bg-brand-soft text-brand' => $loop->first,
                        'bg-sunken text-body hover:bg-brand-soft' => ! $loop->first,
                    ])
                    style="font-size: 12px; padding: 5px 12px; {{ $loop->index > 2 ? 'display: none' : '' }}"
                >
                    <span class="pill-live-dot"></span>{{ $rubro['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- glow jade --}}
    <div
        class="absolute"
        style="
            inset: -12% -8%;
            background: radial-gradient(
                60% 55% at 60% 35%,
                color-mix(in srgb, var(--brand) 28%, transparent),
                transparent 70%
            );
            filter: blur(8px);
            z-index: 0;
        "
    ></div>

    {{-- Bezel in surface + hairline ring, same family as the dashboard
    phones: a near-black frame swallowed the hero in light mode. --}}
    <div
        class="relative mx-auto"
        style="
            z-index: 1;
            width: 300px;
            background: var(--surface-card);
            border-radius: 36px;
            padding: 10px;
            box-shadow:
                0 0 0 1px var(--border-default),
                var(--shadow-xl);
        "
    >
        <div style="background: var(--chat-canvas); border-radius: 28px; overflow: hidden">
            {{-- Status bar: the tiny realism cue; hero.js sets the real time. --}}
            <div
                class="flex items-center justify-between"
                style="
                    background: var(--bubble-out);
                    padding: 8px 16px 0;
                    color: color-mix(in srgb, var(--bubble-out-text) 90%, transparent);
                    font-family: var(--font-mono);
                    font-size: 11px;
                "
            >
                <span data-hero-clock>09:41</span>
                <span class="flex items-center gap-1.5">
                    <x-icon name="signal" :size="12" />
                    5G
                    <x-icon name="battery-full" :size="14" />
                </span>
            </div>

            {{-- header --}}
            <div class="flex items-center gap-2.5" style="background: var(--bubble-out); padding: 10px 14px 12px">
                <span
                    class="inline-flex items-center justify-center"
                    style="width: 36px; height: 36px; border-radius: 50%; background: rgba(255, 255, 255, 0.18)"
                >
                    <x-icon name="bot" :size="20" style="color: var(--bubble-out-text)" />
                </span>
                <div style="line-height: 1.2">
                    <div data-demo-header style="color: var(--bubble-out-text); font-weight: 700; font-size: 14px">
                        {{ __('landing.phone.header') }}
                    </div>
                    <div
                        class="flex items-center gap-1.5"
                        style="color: color-mix(in srgb, var(--bubble-out-text) 80%, transparent); font-size: 11px"
                    >
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #7cffc4"></span
                        >{{ __('landing.phone.online') }}
                    </div>
                </div>
            </div>

            {{-- mensajes --}}
            <div
                class="flex flex-col"
                data-phone-live
                data-live-pool="{{ json_encode($livePool, JSON_UNESCAPED_UNICODE) }}"
                style="padding: 14px 12px; gap: 9px; min-height: 300px"
            >
                @foreach ($bubbles as $b)
                    <div class="pm-row {{ $b['side'] }}">
                        <div class="pm-bubble {{ $b['side'] }} phone-bubble phone-bubble-{{ $loop->iteration }}">
                            {!! $b['text'] !!}
                            <span class="pm-time">{{ $b['time'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- The interactive leg: the visitor writes as the customer and
            the REAL assistant answers. hero.js drives it; the scripted pool
            yields the moment the first real message lands. --}}
            <div
                data-demo
                data-endpoint="{{ route('demo.message') }}"
                data-limit-reply="{{ __('landing.demo.limit_reply') }}"
                data-error-reply="{{ __('landing.demo.error_reply') }}"
                data-share-template="{{ __('landing.demo.share_text', ['name' => '__NAME__', 'chat' => '__CHAT__', 'url' => url('/')]) }}"
                class="bd-subtle border-t"
                style="background: var(--surface-card); padding: 8px 10px 12px"
            >
                <p class="text-subtle" style="font-size: 11px; margin-bottom: 6px">
                    {{ __('landing.demo.try_label') }}
                </p>
                @foreach (__('landing.demo.rubros') as $slug => $rubro)
                    <div
                        data-demo-chips="{{ $slug }}"
                        class="flex gap-1.5 overflow-x-auto"
                        style="margin-bottom: 8px; scrollbar-width: none; {{ $loop->first ? '' : 'display: none' }}"
                    >
                        @foreach ($rubro['chips'] as $chip)
                            <button
                                type="button"
                                data-demo-chip
                                class="bg-sunken text-body hover:bg-brand-soft flex-none rounded-full transition-colors"
                                style="font-size: 11px; padding: 4px 10px"
                            >
                                {{ $chip }}
                            </button>
                        @endforeach
                    </div>
                @endforeach
                <form data-demo-form class="flex items-center gap-2">
                    <div class="min-w-0 flex-1">
                        <x-ui.input
                            size="sm"
                            name="demo_message"
                            maxlength="200"
                            autocomplete="off"
                            data-demo-input
                            :placeholder="__('landing.demo.placeholder')"
                            :aria-label="__('landing.demo.placeholder')"
                        />
                    </div>
                    <x-ui.icon-button icon="send" size="sm" :label="__('landing.demo.send')" data-demo-send />
                </form>
                {{-- Trial transparency that nudges: how many tries are left. --}}
                <p
                    data-demo-left
                    data-left-one="{{ trans_choice('landing.demo.left', 1, ['count' => 1]) }}"
                    data-left-many="{{ trans_choice('landing.demo.left', 2, ['count' => '__N__']) }}"
                    class="text-subtle"
                    style="display: none; font-size: 10px; margin-top: 6px; text-align: center"
                ></p>
                <a
                    data-demo-cta
                    href="{{ Route::has('register') ? route('register') : '#' }}"
                    class="btn btn-primary btn-sm w-full"
                    style="display: none; margin-top: 8px"
                >{{ __('landing.demo.cta_more') }}</a>
                {{-- The visitor becomes the megaphone: the chat, shared on
                the very channel the product lives on. --}}
                {{-- Born without href on purpose: finish() builds the wa.me
                link from the real transcript before showing it. --}}
                <a
                    data-demo-share
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-secondary btn-sm w-full"
                    style="display: none; margin-top: 8px"
                >{{ __('landing.demo.share') }}</a>
            </div>
        </div>
    </div>
</div>
