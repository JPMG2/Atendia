@php
    // A whole array comes back raw from lang: the trial length is filled in here, from the plans table.
    $trialDays = (string) \App\Classes\Main\Plan::trial()->trialDays;
    $faqs = collect(__('landing.faq.items'))
        ->map(fn (array $faq): array => ['q' => $faq['q'], 'a' => str_replace(':days', $trialDays, $faq['a'])])
        ->all();

    // Same door as Pro's pricing CTA; unset number = the line simply hides.
    $salesWhatsapp = config('atendia.sales_whatsapp');

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

        @if ($salesWhatsapp)
            <p class="text-muted mt-6 text-center" style="font-size: var(--text-sm)">
                {{ __('landing.faq.more') }}
                <a
                    href="https://wa.me/{{ $salesWhatsapp }}?text={{ rawurlencode(__('landing.faq.whatsapp_text')) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-brand inline-flex items-center gap-1.5 font-semibold underline-offset-2 hover:underline"
                >
                    <x-icon name="message-circle" :size="16" />
                    {{ __('landing.faq.more_cta') }}
                </a>
            </p>
        @endif
    </div>
</section>

<script type="application/ld+json">
    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
