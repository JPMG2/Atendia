@php
    // The screen fills these in; while nobody has, the copy that shipped stands
    // in. A footer that empties out because a record is missing is worse than
    // one that shows the default.
    $company = \App\Models\Company::current();

    // Only anchors that exist: a footer of dead links reads as abandonment.
    // Help, blog and the legal pages join here the day they are real.
    $cols = [
        ['h' => __('landing.footer.col_product'), 'links' => [
            '#funciones' => __('landing.footer.link_features'),
            '#como-funciona' => __('landing.footer.link_how'),
            '#casos' => __('landing.footer.link_cases'),
            '#precios' => __('landing.footer.link_pricing'),
            '#preguntas' => __('landing.footer.link_faq'),
        ]],
    ];

    // Nobody pays a brand they cannot reach: a human line, an address and a
    // monitored mailbox are what say "this is a real company" on a page that
    // is asking a stranger for a subscription. Each one hides if it is empty.
    $whatsapp = \App\Models\Company::whatsapp();
    $region = $company?->region;
    $place = collect([$region?->name, $region?->province?->name, $region?->province?->country?->name])
        ->filter()
        ->implode(', ');

    $locales = config('locales.supported');
    $labels = config('locales.labels');
    $current = app()->getLocale();
@endphp

<footer class="bg-card bd-subtle border-t">
    <div
        class="mx-auto grid grid-cols-2 gap-8 lg:grid-cols-[1.4fr_1fr_1fr]"
        style="max-width: var(--container-xl); padding: 48px 24px 28px"
    >
        <div class="col-span-2 flex flex-col gap-3 lg:col-span-1" style="max-width: 280px">
            <x-site.logo :size="24" />
            <p class="text-muted" style="font-size: var(--text-sm); line-height: 1.55">
                {{ $company?->tagline ?: __('landing.footer.tagline') }}
            </p>

            {{-- The order is the one the screen set: `socialLinks` comes back
            sorted by `sort_order`, which is what that column is for. --}}
            @if ($company?->socialLinks->isNotEmpty())
                <div class="flex items-center gap-3">
                    @foreach ($company->socialLinks as $link)
                        <a
                            href="{{ $link->url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-subtle hover:text-brand transition"
                            aria-label="{{ $link->socialNetwork?->name }}"
                        >
                            <x-icon :name="$link->socialNetwork?->icon ?: 'link'" :size="18" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        @foreach ($cols as $col)
            <div class="flex flex-col gap-2.5">
                <div class="text-strong" style="font-weight: 700; font-size: var(--text-sm)">{{ $col['h'] }}</div>
                @foreach ($col['links'] as $anchor => $l)
                    <a
                        href="{{ $anchor }}"
                        class="text-muted hover:text-brand transition"
                        style="font-size: var(--text-sm)"
                    >{{ $l }}</a>
                @endforeach
            </div>
        @endforeach

        @if ($whatsapp || $company?->email || $company?->address)
            <div class="col-span-2 flex flex-col gap-2.5 lg:col-span-1">
                <div class="text-strong" style="font-weight: 700; font-size: var(--text-sm)">
                    {{ __('landing.footer.col_contact') }}
                </div>

                @if ($whatsapp)
                    <a
                        href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode(__('landing.footer.whatsapp_text')) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-muted hover:text-brand inline-flex items-center gap-2 transition"
                        style="font-size: var(--text-sm)"
                    >
                        <x-icon name="message-circle" :size="15" />
                        {{ __('landing.footer.whatsapp') }}
                    </a>
                @endif

                @if ($company?->email)
                    <a
                        href="mailto:{{ $company->email }}"
                        class="text-muted hover:text-brand inline-flex items-center gap-2 transition"
                        style="font-size: var(--text-sm)"
                    >
                        <x-icon name="mail" :size="15" />
                        {{ $company->email }}
                    </a>
                @endif

                @if ($company?->address)
                    <span class="text-muted inline-flex items-start gap-2" style="font-size: var(--text-sm)">
                        <x-icon name="map-pin" :size="15" class="mt-0.5 flex-none" />
                        <span
                            >{{ $company->address }}
                            @if ($place)
                                <br
                                />{{ $place }}
                            @endif
                        </span>
                    </span>
                @endif
            </div>
        @endif
    </div>
    <div
        class="bd-subtle text-subtle mx-auto flex flex-wrap items-center justify-between gap-2.5 border-t"
        style="max-width: var(--container-xl); padding: 16px 24px; font-size: var(--text-xs)"
    >
        <span>© {{ date('Y') }} {{ $company?->legal_name ?: \App\Models\Company::brand() }}. {{ $company?->text_copyright ?: __('landing.footer.copyright') }}</span>
        <span class="flex items-center gap-3.5">
            {{-- Language picker: geolocation suggests, the person decides. --}}
            <span x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = ! open"
                    @click.outside="open = false"
                    class="text-subtle hover:text-brand inline-flex items-center gap-1.5 transition"
                    style="font-size: var(--text-xs)"
                >
                    <x-icon name="globe" :size="14" />
                    {{ $labels[$current] ?? __('landing.footer.language') }}
                    <x-icon name="chevron-down" :size="12" />
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-transition
                    class="bg-card bd-subtle absolute bottom-full right-0 mb-2 overflow-hidden rounded-lg border"
                    style="z-index: var(--z-sticky); min-width: 160px; box-shadow: var(--shadow-md)"
                >
                    @foreach ($locales as $loc)
                        <a
                            href="{{ route('locale.switch', $loc) }}"
                            class="block px-3 py-2 text-body hover:bg-sunken transition {{ $loc === $current ? 'text-brand font-semibold' : '' }}"
                            style="font-size: var(--text-sm)"
                        >{{ $labels[$loc] ?? $loc }}</a>
                    @endforeach
                </div>
            </span>
        </span>
    </div>
</footer>
