<section class="w-full flex justify-center pt-6 pb-20">
    <div class="w-full px-6" style="max-width: var(--container-xl);">
        <div class="relative overflow-hidden text-center" style="background: var(--jade-700); border-radius:var(--radius-2xl); padding:56px 40px; box-shadow: var(--shadow-lg);">
            <div class="absolute inset-0" style="background: radial-gradient(70% 120% at 80% 0%, rgba(255,126,90,.35), transparent 60%);"></div>
            <div class="relative flex flex-col items-center gap-4.5" style="gap:18px;">
                <h2 class="font-display" style="font-size: var(--text-5xl); color:#fff; max-width:620px; letter-spacing:-0.03em;">{{ __('landing.closing.title') }}</h2>
                <p style="color:rgba(255,255,255,.86); font-size: var(--text-lg); max-width:520px;">{{ __('landing.closing.subtitle') }}</p>
                <div class="flex flex-wrap gap-3 justify-center">
                    <x-ui.button variant="accent" size="lg" icon="zap" :href="Route::has('register') ? route('register') : '#'">{{ __('landing.closing.cta_primary') }}</x-ui.button>
                    <x-ui.button variant="secondary" size="lg" href="#como-funciona" style="background:transparent;color:#fff;border-color:rgba(255,255,255,.4);">{{ __('landing.closing.cta_secondary') }}</x-ui.button>
                </div>
            </div>
        </div>
    </div>
</section>
