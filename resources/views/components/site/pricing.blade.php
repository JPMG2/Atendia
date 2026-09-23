@php
    $registerHref = Route::has('register') ? route('register') : '#';

    // The best demo of the product is the product: Pro's CTA opens a real
    // WhatsApp chat with sales. Unset number = quiet fallback to register.
    $salesWhatsapp = config('atendia.sales_whatsapp');
    $salesHref = $salesWhatsapp
        ? 'https://wa.me/'.$salesWhatsapp.'?text='.rawurlencode(__('landing.pricing.premium.whatsapp_text'))
        : $registerHref;

    // The three REAL packages (caps provisional until the measured AI cost
    // settles them). Yearly = 2 months free: paying 10 of 12.
    $plans = [
        ['name' => __('landing.pricing.emprende.name'), 'price' => '$29', 'price_year' => '$24', 'save_year' => '$58', 'per' => __('landing.pricing.per_month'), 'per_year' => __('landing.pricing.per_month_yearly'), 'desc' => __('landing.pricing.emprende.desc'), 'includes' => null, 'feats' => __('landing.pricing.emprende.feats'), 'cta' => __('landing.pricing.emprende.cta'), 'variant' => 'secondary', 'featured' => false, 'href' => $registerHref, 'external' => false],
        ['name' => __('landing.pricing.negocio.name'), 'price' => '$79', 'price_year' => '$66', 'save_year' => '$158', 'per' => __('landing.pricing.per_month'), 'per_year' => __('landing.pricing.per_month_yearly'), 'desc' => __('landing.pricing.negocio.desc'), 'includes' => __('landing.pricing.negocio.includes'), 'feats' => __('landing.pricing.negocio.feats'), 'cta' => __('landing.pricing.negocio.cta'), 'variant' => 'primary', 'featured' => true, 'href' => $registerHref, 'external' => false],
        ['name' => __('landing.pricing.premium.name'), 'price' => '$149', 'price_year' => '$124', 'save_year' => '$298', 'per' => __('landing.pricing.per_month'), 'per_year' => __('landing.pricing.per_month_yearly'), 'desc' => __('landing.pricing.premium.desc'), 'includes' => __('landing.pricing.premium.includes'), 'feats' => __('landing.pricing.premium.feats'), 'cta' => __('landing.pricing.premium.cta'), 'variant' => 'secondary', 'featured' => false, 'href' => $salesHref, 'external' => (bool) $salesWhatsapp],
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
                        {{-- In every tier by the owner's call: the languages
                        plus is the pitch, nobody should have to infer it. --}}
                        <div
                            class="text-body flex items-center gap-2.5 font-semibold"
                            style="font-size: var(--text-sm)"
                        >
                            <x-icon
                                name="languages"
                                :size="16"
                                style="color: var(--brand)"
                            />{{ __('landing.pricing.multilang') }}
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
            @endforeach
        </div>

        @php
            // The calculator is plain arithmetic on the visitor's own input:
            // no invented market stats, and the plan it names reuses the real
            // caps and prices, so "each hour costs you" is honest math.
            $calcMinutes = (int) config('atendia.calculator_minutes');
            $calcPlans = collect(['emprende', 'negocio', 'premium'])->map(fn (string $key): array => [
                'name' => __("landing.pricing.{$key}.name"),
                'price' => (int) config("atendia.plans.{$key}.price"),
                'cap' => (int) config("atendia.plans.{$key}.conversations_per_month"),
            ])->all();
            $calcDays = [
                'one' => __('landing.pricing.calculator.days_one'),
                'exact' => __('landing.pricing.calculator.days_exact'),
                'almost' => __('landing.pricing.calculator.days_almost'),
                'over' => __('landing.pricing.calculator.days_over'),
            ];
            $calcCosts = [
                'today' => __('landing.pricing.calculator.today_cost'),
                'with' => __('landing.pricing.calculator.with_cost'),
            ];
        @endphp

        <div
            class="mx-auto mt-10"
            style="max-width: 720px"
            x-data="{
                perDay: 20,
                hourValue: 5,
                minutes: {{ $calcMinutes }},
                plans: @js($calcPlans),
                days: @js($calcDays),
                costs: @js($calcCosts),
                get todayCost() { return Math.round(this.hours * this.hourValue) },
                get saving() { return this.todayCost - this.plan.price },
                get perMonth() { return this.perDay * 30 },
                get hours() { return (this.perMonth * this.minutes) / 60 },
                get plan() { return this.plans.find((p) => this.perMonth <= p.cap) ?? this.plans[this.plans.length - 1] },
                get workDays() {
                    const ratio = this.hours / 8, whole = Math.floor(ratio);
                    if (ratio < 1.5) return this.days.one;
                    if (ratio === whole) return this.days.exact.replace(':n', whole);
                    return ratio - whole >= 0.5
                        ? this.days.almost.replace(':n', Math.ceil(ratio))
                        : this.days.over.replace(':n', whole);
                },
                get perHour() {
                    return '$' + (this.plan.price / this.hours).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            }"
        >
            <x-ui.card style="padding: 24px">
                <h3 class="mb-1 text-center font-display" style="font-size: var(--text-xl)">
                    {{ __('landing.pricing.calculator.title') }}
                </h3>
                <p class="text-muted mb-5 text-center" style="font-size: var(--text-sm)">
                    {{ __('landing.pricing.calculator.subtitle') }}
                </p>

                <x-ui.range
                    :label="__('landing.pricing.calculator.slider_label')"
                    min="5"
                    max="100"
                    step="5"
                    x-model.number="perDay"
                />
                <p
                    class="text-brand mt-1 font-mono font-semibold"
                    style="font-size: var(--text-sm)"
                    x-text="perDay"
                ></p>

                <p class="calc-formula">
                    <b class="font-mono" x-text="perDay"></b>
                    {{ __('landing.pricing.calculator.formula_per_day') }}
                    <b class="font-mono" x-text="perMonth.toLocaleString('es')"></b>
                    {{ __('landing.pricing.calculator.formula_month') }}
                </p>

                <x-ui.range
                    :label="__('landing.pricing.calculator.hour_value_label')"
                    min="2"
                    max="30"
                    step="1"
                    x-model.number="hourValue"
                />
                <p
                    class="text-brand mb-4 mt-1 font-mono font-semibold"
                    style="font-size: var(--text-sm)"
                    x-text="'$' + hourValue"
                ></p>

                <div class="calc-vs">
                    <div class="calc-side calc-side-today">
                        <span class="calc-tag">{{ __('landing.pricing.calculator.today_tag') }}</span>
                        <b class="calc-big font-mono" x-text="Math.round(hours) + ' h'"></b>
                        <p class="calc-lead">{{ __('landing.pricing.calculator.today_lead') }}</p>
                        <p x-text="workDays"></p>
                        <p class="calc-cost" x-text="costs.today.replace(':amount', '$' + todayCost)"></p>
                    </div>
                    <div class="calc-side calc-side-with">
                        <span class="calc-tag">{{ __('landing.pricing.calculator.with_tag') }}</span>
                        <b class="calc-big font-mono">0 h</b>
                        <p class="calc-lead">{{ __('landing.pricing.calculator.with_lead') }}</p>
                        <p>{{ __('landing.pricing.calculator.with_note') }}</p>
                        <p
                            class="calc-cost"
                            x-text="costs.with.replace(':plan', plan.name).replace(':amount', '$' + plan.price)"
                        ></p>
                    </div>
                </div>

                <p class="calc-verdict">
                    {{ __('landing.pricing.calculator.verdict_recover') }}
                    <b class="font-mono" x-text="Math.round(hours) + ' h'"></b>
                    {{ __('landing.pricing.calculator.verdict_hours') }}<span x-show="saving > 0">
                        {{ __('landing.pricing.calculator.verdict_save') }}
                        <b class="text-brand font-mono" x-text="'$' + saving"></b></span
                    >.<br />
                    {{ __('landing.pricing.calculator.verdict_per_hour') }}
                    <b class="text-brand font-mono" x-text="perHour"></b>.
                </p>

                {{-- The calculator convinces; without a next step the visitor stalls here. --}}
                <div class="mt-4 flex justify-center">
                    <x-ui.button variant="primary" size="md" :href="$registerHref">
                        {{ __('landing.pricing.calculator.cta') }}</x-ui.button>
                </div>

                <p class="text-subtle mt-3 text-center" style="font-size: var(--text-xs)">
                    {{ __('landing.pricing.calculator.assumption', ['minutes' => $calcMinutes]) }}
                </p>
            </x-ui.card>
        </div>

        <p class="text-muted mt-8 text-center" style="font-size: var(--text-sm)">
            {{ __('landing.pricing.trust') }} · {{ __('landing.pricing.currency_note') }}
        </p>

        @php
            // LatAm buyers read USD as stable, so USD stays canonical; when the
            // owner sets a reference rate for the visitor's region, one line
            // grounds the featured plan in the local currency.
            $reference = config('atendia.pricing_reference.'.app()->getLocale());
        @endphp

        @if ($reference && $reference['rate'])
            <p class="text-subtle mt-2 text-center" style="font-size: var(--text-xs)">
                {{
                    __('landing.pricing.local_reference', [
                        'plan' => __('landing.pricing.negocio.name'),
                        'amount' => $reference['symbol'].' '.number_format(config('atendia.plans.negocio.price') * $reference['rate'], 0, ',', '.'),
                    ])
                }}
            </p>
        @endif
    </div>
</section>
