{{-- Phone-only sticky register bar: appears once the hero (and its primary
CTA) scrolls out of view, so the main action is never more than a thumb away. --}}
<div
    class="mobile-cta lg:hidden"
    x-data="{ shown: false }"
    x-init="
        new IntersectionObserver(([entry]) => (shown = ! entry.isIntersecting)).observe(document.getElementById('top'))
    "
    x-show="shown"
    x-cloak
    x-transition:enter="transition duration-200"
    x-transition:enter-start="opacity-0 translate-y-3"
    x-transition:enter-end="opacity-100 translate-y-0"
>
    <x-ui.button
        variant="primary"
        size="lg"
        icon="zap"
        fullWidth
        :href="Route::has('register') ? route('register') : '#'"
    >
        {{ __('landing.nav.register') }}</x-ui.button>
</div>
