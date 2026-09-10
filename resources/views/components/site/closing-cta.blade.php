@php
    // A pocket echo of the hero phone, mid-conversation: the closing pitch
    // shows the product still answering instead of repeating a promise.
    $bubbles = [
        ['side' => 'in', 'time' => '18:03', 'text' => __('landing.phone.b5')],
        ['side' => 'out', 'time' => '18:03', 'text' => __('landing.phone.b6')],
        ['side' => 'in', 'time' => '18:04', 'text' => __('landing.phone.b7')],
        ['side' => 'out', 'time' => '18:04', 'text' => __('landing.phone.b8')],
    ];
@endphp

<section class="flex w-full justify-center pb-20 pt-6">
    <div class="w-full px-6" style="max-width: var(--container-xl)">
        <div
            class="relative overflow-hidden"
            style="
                background: var(--jade-700);
                border-radius: var(--radius-2xl);
                padding: 56px 40px;
                box-shadow: var(--shadow-lg);
            "
        >
            <div
                class="absolute inset-0"
                style="background: radial-gradient(70% 120% at 80% 0%, rgba(255, 126, 90, 0.35), transparent 60%)"
            ></div>
            <div class="relative flex flex-col items-center gap-10 text-center lg:flex-row lg:justify-between lg:text-left">
                <div class="flex flex-col items-center lg:items-start" style="gap: 18px">
                    <h2
                        class="font-display"
                        style="font-size: var(--text-5xl); color: #fff; max-width: 620px; letter-spacing: -0.03em"
                    >
                        {{ __('landing.closing.title') }}
                    </h2>
                    <p style="color: rgba(255, 255, 255, 0.86); font-size: var(--text-lg); max-width: 520px">
                        {{ __('landing.closing.subtitle') }}
                    </p>
                    <div class="flex flex-wrap justify-center gap-3 lg:justify-start">
                        <x-ui.button
                            variant="accent"
                            size="lg"
                            icon="zap"
                            :href="Route::has('register') ? route('register') : '#'"
                        >
                            {{ __('landing.closing.cta_primary') }}</x-ui.button>
                        <x-ui.button
                            variant="secondary"
                            size="lg"
                            href="#como-funciona"
                            style="background: transparent; color: #fff; border-color: rgba(255, 255, 255, 0.4)"
                        >
                            {{ __('landing.closing.cta_secondary') }}</x-ui.button>
                    </div>
                </div>

                <div
                    class="closing-phone shrink-0"
                    style="
                        width: 250px;
                        background: var(--surface-card);
                        border-radius: 30px;
                        padding: 8px;
                        box-shadow:
                            0 0 0 1px var(--border-default),
                            var(--shadow-xl);
                    "
                >
                    <div style="background: var(--chat-canvas); border-radius: 24px; overflow: hidden">
                        <div class="flex items-center gap-2" style="background: var(--bubble-out); padding: 9px 12px">
                            <span
                                class="inline-flex items-center justify-center"
                                style="
                                    width: 28px;
                                    height: 28px;
                                    border-radius: 50%;
                                    background: rgba(255, 255, 255, 0.18);
                                "
                            >
                                <x-icon name="bot" :size="16" style="color: var(--bubble-out-text)" />
                            </span>
                            <div style="line-height: 1.2">
                                <div style="color: var(--bubble-out-text); font-weight: 700; font-size: 12px">
                                    {{ __('landing.phone.header') }}
                                </div>
                                <div
                                    class="flex items-center gap-1"
                                    style="
                                        color: color-mix(in srgb, var(--bubble-out-text) 80%, transparent);
                                        font-size: 10px;
                                    "
                                >
                                    <span
                                        class="badge-dot-pulse"
                                        style="width: 6px; height: 6px; border-radius: 50%; background: #7cffc4"
                                    ></span>
                                    {{ __('landing.phone.online') }}
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col text-left" style="padding: 12px 10px; gap: 8px">
                            @foreach ($bubbles as $b)
                                <div class="pm-row {{ $b['side'] }}">
                                    <div class="pm-bubble {{ $b['side'] }}">
                                        {!! $b['text'] !!}
                                        <span class="pm-time">{{ $b['time'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
