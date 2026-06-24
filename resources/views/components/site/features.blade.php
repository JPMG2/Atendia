@php
    $items = [
        ['icon' => 'calendar-check', 'title' => __('landing.features.schedule.title'), 'body' => __('landing.features.schedule.body')],
        ['icon' => 'package', 'title' => __('landing.features.catalog.title'), 'body' => __('landing.features.catalog.body')],
        ['icon' => 'message-circle', 'title' => __('landing.features.always.title'), 'body' => __('landing.features.always.body')],
        ['icon' => 'bell', 'title' => __('landing.features.alerts.title'), 'body' => __('landing.features.alerts.body')],
        ['icon' => 'sliders-horizontal', 'title' => __('landing.features.control.title'), 'body' => __('landing.features.control.body')],
        ['icon' => 'shield-check', 'title' => __('landing.features.brand.title'), 'body' => __('landing.features.brand.body')],
    ];
@endphp

<section id="funciones" class="w-full flex justify-center pt-16 pb-16">
    <div class="w-full px-6" style="max-width: var(--container-xl);">
        <div class="flex flex-col items-center text-center gap-3.5 mb-11">
            <span class="eyebrow eyebrow-line">{{ __('landing.features.eyebrow') }}</span>
            <h2 class="font-display" style="font-size: var(--text-4xl); max-width:620px;">{{ __('landing.features.title') }}</h2>
            <p class="text-muted" style="font-size: var(--text-lg); max-width:560px;">{{ __('landing.features.subtitle') }}</p>
        </div>

        <div class="grid gap-4.5 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap:18px;">
            @foreach ($items as $it)
                <x-ui.card interactive style="padding:24px;">
                    <span class="inline-flex items-center justify-center mb-4" style="width:46px;height:46px;border-radius:var(--radius-md);background:var(--brand-soft);color:var(--brand);">
                        <x-icon :name="$it['icon']" :size="23" />
                    </span>
                    <h3 class="font-display mb-2" style="font-size: var(--text-xl);">{{ $it['title'] }}</h3>
                    <p class="text-muted" style="font-size: var(--text-sm); line-height:1.55;">{{ $it['body'] }}</p>
                </x-ui.card>
            @endforeach
        </div>
    </div>
</section>
