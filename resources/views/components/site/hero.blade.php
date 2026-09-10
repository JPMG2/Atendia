{{-- overflow-x clip: the phone glides in from beyond the right edge and
must never leave a horizontal scrollbar behind. --}}
<section id="top" class="flex w-full justify-center overflow-x-clip pb-16 pt-16">
    <div class="w-full px-6" style="max-width: var(--container-xl)">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:gap-12">
            {{-- Texto --}}
            <div class="flex flex-col gap-5">
                <x-ui.badge variant="brand" dot pulse class="hero-enter hero-enter-1 self-start">{{ __('landing.hero.badge') }}</x-ui.badge>

                <h1
                    class="hero-enter hero-enter-2 font-display"
                    style="font-size: var(--text-6xl); line-height: 1.04; letter-spacing: -0.03em"
                >
                    {{ __('landing.hero.title_1') }}<br /><span
                        class="text-brand hero-type"
                        data-hero-type
                        >{{ __('landing.hero.title_2') }}</span>
                </h1>

                <p class="hero-enter hero-enter-3 text-muted" style="font-size: var(--text-lg); max-width: 460px">
                    {{ __('landing.hero.subtitle') }}
                </p>

                <div class="hero-enter hero-enter-4 flex flex-wrap gap-3">
                    <x-ui.button
                        variant="primary"
                        size="lg"
                        icon="zap"
                        class="cta-spark"
                        :href="Route::has('register') ? route('register') : '#'"
                        style="box-shadow: var(--shadow-brand)"
                    >
                        {{ __('landing.hero.cta_primary') }}</x-ui.button>
                    <x-ui.button
                        variant="secondary"
                        size="lg"
                        icon="play"
                        href="#como-funciona"
                    >
                        {{ __('landing.hero.cta_secondary') }}</x-ui.button>
                </div>

                <div class="hero-enter hero-enter-5 text-muted flex flex-wrap items-center gap-4" style="font-size: var(--text-sm)">
                    <span class="inline-flex items-center gap-1.5"
                        ><x-icon name="check" :size="16" style="color: var(--brand)" />
                        {{ __('landing.hero.perk_trial') }}</span>
                    <span class="inline-flex items-center gap-1.5"
                        ><x-icon name="check" :size="16" style="color: var(--brand)" />
                        {{ __('landing.hero.perk_card') }}</span>
                    <span class="inline-flex items-center gap-1.5"
                        ><x-icon name="check" :size="16" style="color: var(--brand)" />
                        {{ __('landing.hero.perk_any') }}</span>
                </div>

                {{-- Social proof from the real table, hidden below 10: a
                near-empty room would un-charm instead of charming. --}}
                @php($served = App\Models\Business::servedCount())
                @if ($served >= 10)
                    <p
                        class="hero-enter hero-enter-6 text-muted inline-flex items-center gap-1.5"
                        style="font-size: var(--text-sm)"
                    >
                        <x-icon name="store" :size="16" style="color: var(--brand)" />
                        {{ __('landing.hero.social_proof', ['count' => $served]) }}
                    </p>
                @endif
            </div>

            {{-- Phone mockup. --}}
            <x-site.phone-mock />
        </div>
    </div>
</section>
