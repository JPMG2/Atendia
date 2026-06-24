<section id="top" class="w-full flex justify-center pt-16 pb-16">
    <div class="w-full px-6" style="max-width: var(--container-xl);">
        <div class="grid items-center gap-12 lg:gap-12 grid-cols-1 lg:grid-cols-[1.1fr_0.9fr]">
            {{-- Texto --}}
            <div class="flex flex-col gap-5">
                <x-ui.badge variant="brand" dot class="self-start">{{ __('landing.hero.badge') }}</x-ui.badge>

                <h1 class="font-display" style="font-size: var(--text-6xl); line-height:1.04; letter-spacing:-0.03em;">
                    {{ __('landing.hero.title_1') }}<br><span class="text-brand">{{ __('landing.hero.title_2') }}</span>
                </h1>

                <p class="text-muted" style="font-size: var(--text-lg); max-width: 460px;">
                    {{ __('landing.hero.subtitle') }}
                </p>

                <div class="flex flex-wrap gap-3">
                    <x-ui.button variant="primary" size="lg" icon="zap" :href="Route::has('register') ? route('register') : '#'" style="box-shadow: var(--shadow-brand);">{{ __('landing.hero.cta_primary') }}</x-ui.button>
                    <x-ui.button variant="secondary" size="lg" icon="play" href="#como-funciona">{{ __('landing.hero.cta_secondary') }}</x-ui.button>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-muted" style="font-size: var(--text-sm);">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="check" :size="16" style="color:var(--brand);" /> {{ __('landing.hero.perk_trial') }}</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="check" :size="16" style="color:var(--brand);" /> {{ __('landing.hero.perk_card') }}</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="check" :size="16" style="color:var(--brand);" /> {{ __('landing.hero.perk_any') }}</span>
                </div>
            </div>

            {{-- Mock de teléfono --}}
            <x-site.phone-mock />
        </div>
    </div>
</section>
