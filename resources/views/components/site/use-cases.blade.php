@php
    $cases = [
        ['tint' => 'brand', 'icon' => 'stethoscope', 'who' => __('landing.cases.health.who'), 'title' => __('landing.cases.health.title'), 'body' => __('landing.cases.health.body'), 'tags' => __('landing.cases.health.tags')],
        ['tint' => 'accent', 'icon' => 'candy', 'who' => __('landing.cases.shop.who'), 'title' => __('landing.cases.shop.title'), 'body' => __('landing.cases.shop.body'), 'tags' => __('landing.cases.shop.tags')],
    ];
@endphp

<section id="casos" class="w-full flex justify-center pt-16 pb-16">
    <div class="w-full px-6" style="max-width: var(--container-xl);">
        <div class="flex flex-col items-center text-center gap-3 mb-10">
            <span class="eyebrow eyebrow-line">{{ __('landing.cases.eyebrow') }}</span>
            <h2 class="font-display" style="font-size: var(--text-4xl); max-width:560px;">{{ __('landing.cases.title') }}</h2>
        </div>

        <div class="grid gap-5 grid-cols-1 md:grid-cols-2">
            @foreach ($cases as $c)
                @php $isAccent = $c['tint'] === 'accent'; @endphp
                <x-ui.card class="flex flex-col gap-3.5" style="border-radius:var(--radius-xl); padding:28px;">
                    <span class="inline-flex items-center justify-center" style="width:52px;height:52px;border-radius:var(--radius-lg);
                        background: {{ $isAccent ? 'var(--accent-soft)' : 'var(--brand-soft)' }}; color: {{ $isAccent ? 'var(--accent)' : 'var(--brand)' }};">
                        <x-icon :name="$c['icon']" :size="26" />
                    </span>
                    <div>
                        <div class="text-subtle" style="font-size: var(--text-xs); font-weight:700; letter-spacing:0.08em; text-transform:uppercase; margin-bottom:4px;">{{ $c['who'] }}</div>
                        <h3 class="font-display" style="font-size: var(--text-2xl);">{{ $c['title'] }}</h3>
                    </div>
                    <p class="text-muted" style="font-size: var(--text-base); line-height:1.55;">{{ $c['body'] }}</p>
                    <div class="flex flex-wrap gap-2 mt-0.5">
                        @foreach ($c['tags'] as $t)
                            <x-ui.badge :variant="$isAccent ? 'accent' : 'brand'">{{ $t }}</x-ui.badge>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    </div>
</section>
