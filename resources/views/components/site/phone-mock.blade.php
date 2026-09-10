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

<div class="relative hero-phone-enter">
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
            box-shadow: 0 0 0 1px var(--border-default), var(--shadow-xl);
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
                    <div style="color: var(--bubble-out-text); font-weight: 700; font-size: 14px">{{ __('landing.phone.header') }}</div>
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
        </div>
    </div>
</div>
