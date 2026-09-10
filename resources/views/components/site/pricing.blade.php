@php
    $registerHref = Route::has('register') ? route('register') : '#';

    // The best demo of the product is the product: Pro's CTA opens a real
    // WhatsApp chat with sales. Unset number = quiet fallback to register.
    $salesWhatsapp = config('atendia.sales_whatsapp');
    $salesHref = $salesWhatsapp
        ? 'https://wa.me/'.$salesWhatsapp.'?text='.rawurlencode(__('landing.pricing.pro.whatsapp_text'))
        : $registerHref;

    // Yearly = 2 months free: the monthly equivalent of paying 10 of 12.
    $plans = [
        ['name' => __('landing.pricing.starter.name'), 'price' => '$0', 'price_year' => '$0', 'per' => __('landing.pricing.per_trial'), 'per_year' => __('landing.pricing.per_trial'), 'desc' => __('landing.pricing.starter.desc'), 'includes' => null, 'feats' => __('landing.pricing.starter.feats'), 'cta' => __('landing.pricing.starter.cta'), 'variant' => 'secondary', 'featured' => false, 'href' => $registerHref, 'external' => false],
        ['name' => __('landing.pricing.business.name'), 'price' => '$29', 'price_year' => '$24', 'save_year' => '$58', 'per' => __('landing.pricing.per_month'), 'per_year' => __('landing.pricing.per_month_yearly'), 'desc' => __('landing.pricing.business.desc'), 'includes' => __('landing.pricing.business.includes'), 'feats' => __('landing.pricing.business.feats'), 'cta' => __('landing.pricing.business.cta'), 'variant' => 'primary', 'featured' => true, 'href' => $registerHref, 'external' => false],
        ['name' => __('landing.pricing.pro.name'), 'price' => '$79', 'price_year' => '$66', 'save_year' => '$158', 'per' => __('landing.pricing.per_month'), 'per_year' => __('landing.pricing.per_month_yearly'), 'desc' => __('landing.pricing.pro.desc'), 'includes' => __('landing.pricing.pro.includes'), 'feats' => __('landing.pricing.pro.feats'), 'cta' => __('landing.pricing.pro.cta'), 'variant' => 'secondary', 'featured' => false, 'href' => $salesHref, 'external' => (bool) $salesWhatsapp],
    ];
@endphp

<section id="precios" class="flex w-full justify-center pb-20 pt-16" x-data="{ yearly: false }">
    <div class="w-full px-6" style="max-width: var(--container-xl)">
        <div class="mb-11 flex flex-col items-center gap-3 text-center">
            <span class="eyebrow eyebrow-line">{{ __('landing.pricing.eyebrow') }}</span>
            <h2 class="font-display" style="font-size: var(--text-4xl); max-width: 560px">
                {{ __('landing.pricing.title') }}
            </h2>
            <p class="text-muted" style="font-size: var(--text-lg)">{{ __('landing.pricing.subtitle') }}</p>

            <div class="pricing-period mt-3" role="group" aria-label="{{ __('landing.pricing.eyebrow') }}">
                <button
                    type="button"
                    class="pricing-period-btn"
                    :class="! yearly && 'is-active'"
                    @click="yearly = false"
                >
                    {{ __('landing.pricing.billing_monthly') }}
                </button>
                <button type="button" class="pricing-period-btn" :class="yearly && 'is-active'" @click="yearly = true">
                    {{ __('landing.pricing.billing_yearly') }}
                    <x-ui.badge variant="brand" ::class="yearly && 'badge-bounce'">
                        {{ __('landing.pricing.billing_yearly_badge') }}</x-ui.badge>
                </button>
            </div>
        </div>

        <div class="pricing-grid">
            @foreach ($plans as $p)
                <div @class([
                    'pricing-tier',
                    'pricing-tier-featured' => $p['featured'],
                    'pricing-tier-left' => $loop->first,
                    'pricing-tier-right' => $loop->last,
                ])>
                    <div class="flex items-center justify-between gap-3">
                        <h3 @class(['font-display', 'text-brand' => $p['featured']]) style="font-size: var(--text-xl)">
                            {{ $p['name'] }}
                        </h3>
                        @if ($p['featured'])
                            <x-ui.badge variant="brand">{{ __('landing.pricing.featured_badge') }}</x-ui.badge>
                        @endif
                    </div>

                    <p class="text-muted" style="font-size: var(--text-sm)">{{ $p['desc'] }}</p>

                    @if ($p['price_year'] === $p['price'])
                        <div class="flex items-baseline gap-1.5">
                            <span
                                class="text-strong font-display"
                                style="font-size: var(--text-5xl); font-weight: 800; letter-spacing: -0.03em"
                            >{{ $p['price'] }}</span>
                            <span class="text-muted" style="font-size: var(--text-sm)">{{ $p['per'] }}</span>
                        </div>
                    @else
                        {{-- Leave is instant on purpose: two prices overlapping shift the row. --}}
                        <div
                            class="flex items-baseline gap-1.5"
                            x-show="! yearly"
                            x-transition:enter="transition duration-200"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                        >
                            <span
                                class="text-strong font-display"
                                style="font-size: var(--text-5xl); font-weight: 800; letter-spacing: -0.03em"
                            >{{ $p['price'] }}</span>
                            <span class="text-muted" style="font-size: var(--text-sm)">{{ $p['per'] }}</span>
                        </div>
                        <div
                            x-show="yearly"
                            x-cloak
                            x-transition:enter="transition duration-200"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                        >
                            <div class="flex items-baseline gap-1.5">
                                <span
                                    class="text-strong font-display"
                                    style="font-size: var(--text-5xl); font-weight: 800; letter-spacing: -0.03em"
                                >{{ $p['price_year'] }}</span>
                                <span class="text-muted" style="font-size: var(--text-sm)">{{ $p['per_year'] }}</span>
                            </div>
                            <p class="text-brand mt-1 font-semibold" style="font-size: var(--text-xs)">
                                {{ __('landing.pricing.save_yearly', ['amount' => $p['save_year']]) }}
                            </p>
                        </div>
                    @endif

                    <div class="flex flex-col gap-2.5">
                        @if ($p['includes'])
                            <p class="text-muted font-semibold" style="font-size: var(--text-sm)">
                                {{ $p['includes'] }}
                            </p>
                        @endif
                        @foreach ($p['feats'] as $f)
                            <div class="text-body flex items-center gap-2.5" style="font-size: var(--text-sm)">
                                <x-icon name="check" :size="16" style="color: var(--brand)" />{{ $f }}
                            </div>
                        @endforeach
                    </div>

                    <x-ui.button
                        :variant="$p['variant']"
                        size="md"
                        fullWidth
                        class="mt-2"
                        :href="$p['href']"
                        :target="$p['external'] ? '_blank' : null"
                        :rel="$p['external'] ? 'noopener' : null"
                    >
                        {{ $p['cta'] }}</x-ui.button>
                </div>
            @endforeach
        </div>

        <p class="text-muted mt-8 text-center" style="font-size: var(--text-sm)">{{ __('landing.pricing.trust') }}</p>
    </div>
</section>
