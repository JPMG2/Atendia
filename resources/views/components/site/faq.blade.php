@php
    $faqs = __('landing.faq.items');

    // The same items feed the visible accordion and the FAQPage structured
    // data: Google lifts these into rich results, one copy of the truth.
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($faqs)
            ->map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
            ])
            ->values()
            ->all(),
    ];
@endphp

<section id="preguntas" class="flex w-full justify-center pb-16 pt-2">
    <div class="w-full px-6" style="max-width: 760px">
        <div class="mb-8 flex flex-col items-center gap-3 text-center">
            <span class="eyebrow eyebrow-line">{{ __('landing.faq.eyebrow') }}</span>
            <h2 class="font-display" style="font-size: var(--text-4xl)">{{ __('landing.faq.title') }}</h2>
        </div>

        <x-ui.card class="px-5 py-1" x-data="{ open: null }">
            @foreach ($faqs as $i => $faq)
                <div @class(['bd-subtle border-t' => ! $loop->first])>
                    <button
                        type="button"
                        class="hover:text-brand flex w-full items-center justify-between gap-4 py-3.5 text-left transition-colors"
                        x-on:click="open = open === {{ $i }} ? null : {{ $i }}"
                        x-bind:aria-expanded="open === {{ $i }}"
                    >
                        <span
                            class="text-strong font-semibold"
                            style="font-size: var(--text-base)"
                        >{{ $faq['q'] }}</span>
                        <span
                            class="flex-none transition-transform"
                            x-bind:style="open === {{ $i }} ? 'transform: rotate(180deg)' : ''"
                        >
                            <x-icon name="chevron-down" :size="18" />
                        </span>
                    </button>
                    <p
                        x-show="open === {{ $i }}"
                        x-cloak
                        x-transition.opacity.duration.140ms
                        class="text-body pb-4"
                        style="font-size: var(--text-sm); line-height: 1.6; max-width: 640px"
                    >
                        {{ $faq['a'] }}
                    </p>
                </div>
            @endforeach
        </x-ui.card>
    </div>
</section>

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
