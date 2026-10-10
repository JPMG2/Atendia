@props([
    'plan',          // App\Classes\Main\Plan — the tier this card sells
    'previous',      // the tier below it, named in the "includes" line
    'first' => false,
    'last' => false,
])

{{--
    One plan's card, shared by the landing and by the preview in the plans
    master: the owner sees the very markup the visitor will, so a figure she
    types can never be judged on a drawing that differs from the real card.
    It reads the Alpine `yearly` flag of whoever wraps it.
--}}
@php
    $registerHref = Route::has('register') ? route('register') : '#';

    // The best demo of the product is the product: Premium's CTA opens a real
    // WhatsApp chat with sales. Unset number = quiet fallback to register.
    $salesWhatsapp = \App\Models\Company::whatsapp();
    $symbol = config('atendia.billing.currency_symbol');
    $hasSalesPitch = Lang::has("landing.pricing.{$plan->code}.whatsapp_text") && (bool) $salesWhatsapp;

    $p = [
        'name' => __('plan.names.'.$plan->code),
        'price' => $symbol.$plan->price,
        'price_year' => $symbol.$plan->annualMonthlyPrice,
        'save_year' => $symbol.$plan->annualSavings,
        'per' => __('landing.pricing.per_month'),
        'per_year' => __('landing.pricing.per_month_yearly'),
        'desc' => __("landing.pricing.{$plan->code}.desc"),
        'includes' => Lang::has("landing.pricing.{$plan->code}.includes") ? __("landing.pricing.{$plan->code}.includes", ['plan' => __('plan.names.'.$previous->code)]) : null,
        'feats' => [
            ...collect($plan->features)->reject(fn (array $line): bool => $line['key'] === 'ask')->pluck('label')->all(),
            ...(array) __("landing.pricing.{$plan->code}.extras"),
        ],
        'ask' => $plan->askPerMonth,
        'cta' => __("landing.pricing.{$plan->code}.cta"),
        'variant' => $plan->isFeatured ? 'primary' : 'secondary',
        'featured' => $plan->isFeatured,
        // A plan with a sales pitch talks to a person; the rest sign up.
        'href' => $hasSalesPitch
            ? 'https://wa.me/'.$salesWhatsapp.'?text='.rawurlencode(__("landing.pricing.{$plan->code}.whatsapp_text", ['plan' => __('plan.names.'.$plan->code)]))
            : $registerHref,
        'external' => $hasSalesPitch,
    ];
@endphp

<div @class([
    'pricing-tier',
    'pricing-tier-featured' => $p['featured'],
    'pricing-tier-left' => $first,
    'pricing-tier-right' => $last,
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
                {{-- The old monthly price, struck through: the discount reads at a glance. --}}
                <span
                    class="text-subtle line-through"
                    style="font-size: var(--text-xl); font-weight: 600"
                >{{ $p['price'] }}</span>
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

    {{-- The owner's AI is THE product (her call): its own block in the house jade and
    display type — never a line lost among the bullets. --}}
    @if ($p['ask'] > 0)
        <div class="pricing-ai">
            <div class="pricing-ai-head">
                <span class="pricing-ai-icon"><x-icon name="sparkles" :size="18" /></span>
                <span class="pricing-ai-eyebrow">{{ __('landing.pricing.ask_eyebrow') }}</span>
            </div>
            <p class="pricing-ai-title">{{ __('landing.pricing.ask_title') }}</p>
            <p class="pricing-ai-text">{{ __('landing.pricing.ask') }}</p>
            <p class="pricing-ai-cap">
                <span class="pricing-ai-number">{{ $p['ask'] }}</span>
                <span class="pricing-ai-unit">{{ __('landing.pricing.ask_unit') }}</span>
            </p>
        </div>
    @endif

    <div class="flex flex-col gap-2.5">
        @if ($p['includes'])
            <p class="text-muted font-semibold" style="font-size: var(--text-sm)">{{ $p['includes'] }}</p>
        @endif
        @foreach ($p['feats'] as $f)
            <div class="text-body flex items-start gap-2.5" style="font-size: var(--text-sm)">
                <x-icon name="check" :size="16" class="mt-0.5 shrink-0" style="color: var(--brand)" />{{ $f }}
            </div>
        @endforeach
        {{-- In every tier by the owner's call: the languages
        plus is the pitch, nobody should have to infer it. --}}
        <div class="text-body flex items-center gap-2.5 font-semibold" style="font-size: var(--text-sm)">
            <x-icon name="languages" :size="16" style="color: var(--brand)" />{{ __('landing.pricing.multilang') }}
        </div>
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
