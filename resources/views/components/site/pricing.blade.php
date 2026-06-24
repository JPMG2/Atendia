@php
    $plans = [
        ['name' => __('landing.pricing.starter.name'), 'price' => '$0', 'per' => __('landing.pricing.per_trial'), 'desc' => __('landing.pricing.starter.desc'), 'feats' => __('landing.pricing.starter.feats'), 'cta' => __('landing.pricing.starter.cta'), 'variant' => 'secondary', 'featured' => false],
        ['name' => __('landing.pricing.business.name'), 'price' => '$29', 'per' => __('landing.pricing.per_month'), 'desc' => __('landing.pricing.business.desc'), 'feats' => __('landing.pricing.business.feats'), 'cta' => __('landing.pricing.business.cta'), 'variant' => 'primary', 'featured' => true],
        ['name' => __('landing.pricing.pro.name'), 'price' => '$79', 'per' => __('landing.pricing.per_month'), 'desc' => __('landing.pricing.pro.desc'), 'feats' => __('landing.pricing.pro.feats'), 'cta' => __('landing.pricing.pro.cta'), 'variant' => 'secondary', 'featured' => false],
    ];
@endphp

<section id="precios" class="w-full flex justify-center pt-16 pb-20">
    <div class="w-full px-6" style="max-width: var(--container-xl);">
        <div class="flex flex-col items-center text-center gap-3 mb-11">
            <span class="eyebrow eyebrow-line">{{ __('landing.pricing.eyebrow') }}</span>
            <h2 class="font-display" style="font-size: var(--text-4xl); max-width:560px;">{{ __('landing.pricing.title') }}</h2>
            <p class="text-muted" style="font-size: var(--text-lg);">{{ __('landing.pricing.subtitle') }}</p>
        </div>

        <div class="grid gap-4.5 items-stretch grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap:18px;">
            @foreach ($plans as $p)
                <div class="relative bg-card flex flex-col gap-4.5" style="gap:18px;
                    border: {{ $p['featured'] ? '2px solid var(--brand)' : '1px solid var(--border-subtle)' }};
                    border-radius:var(--radius-xl); padding:28px; box-shadow: {{ $p['featured'] ? 'var(--shadow-lg)' : 'var(--shadow-sm)' }};">
                    @if ($p['featured'])
                        <span class="absolute" style="top:-12px;left:24px;background:var(--brand);color:#fff;font-size:11px;font-weight:700;padding:4px 12px;border-radius:999px;">{{ __('landing.pricing.featured_badge') }}</span>
                    @endif
                    <div>
                        <h3 class="font-display mb-2" style="font-size: var(--text-xl);">{{ $p['name'] }}</h3>
                        <div class="flex items-baseline gap-1.5">
                            <span class="font-display text-strong" style="font-size: var(--text-5xl); font-weight:800; letter-spacing:-0.03em;">{{ $p['price'] }}</span>
                            <span class="text-muted" style="font-size: var(--text-sm);">{{ $p['per'] }}</span>
                        </div>
                        <p class="text-muted mt-2" style="font-size: var(--text-sm);">{{ $p['desc'] }}</p>
                    </div>

                    <x-ui.button :variant="$p['variant']" size="md" fullWidth :href="Route::has('register') ? route('register') : '#'">{{ $p['cta'] }}</x-ui.button>

                    <div class="flex flex-col gap-2.5 mt-1">
                        @foreach ($p['feats'] as $f)
                            <div class="flex items-center gap-2.5 text-body" style="font-size: var(--text-sm);">
                                <x-icon name="check" :size="16" style="color:var(--brand);" />{{ $f }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
