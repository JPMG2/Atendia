@php
    $cols = [
        ['h' => __('landing.footer.col_product'), 'links' => __('landing.footer.links_product')],
        ['h' => __('landing.footer.col_resources'), 'links' => __('landing.footer.links_resources')],
        ['h' => __('landing.footer.col_company'), 'links' => __('landing.footer.links_company')],
    ];

    $locales = config('locales.supported');
    $labels = config('locales.labels');
    $current = app()->getLocale();
@endphp

<footer class="bg-card border-t bd-subtle">
    <div class="mx-auto grid gap-8 grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr]" style="max-width: var(--container-xl); padding:48px 24px 28px;">
        <div class="flex flex-col gap-3 col-span-2 lg:col-span-1" style="max-width:280px;">
            <x-site.logo :size="24" />
            <p class="text-muted" style="font-size: var(--text-sm); line-height:1.55;">{{ __('landing.footer.tagline') }}</p>
        </div>
        @foreach ($cols as $col)
            <div class="flex flex-col gap-2.5">
                <div class="text-strong" style="font-weight:700;font-size:var(--text-sm);">{{ $col['h'] }}</div>
                @foreach ($col['links'] as $l)
                    <a href="#" class="text-muted hover:text-brand transition" style="font-size:var(--text-sm);">{{ $l }}</a>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="border-t bd-subtle mx-auto flex flex-wrap justify-between items-center gap-2.5 text-subtle" style="max-width: var(--container-xl); padding:16px 24px; font-size:var(--text-xs);">
        <span>© {{ date('Y') }} Atendia. {{ __('landing.footer.copyright') }}</span>
        <span class="flex items-center gap-3.5">
            <a href="#" class="text-subtle">{{ __('landing.footer.terms') }}</a>
            <a href="#" class="text-subtle">{{ __('landing.footer.privacy') }}</a>

            {{-- Selector de idioma: la geo sugiere, el usuario decide --}}
            <span x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    @click.outside="open = false"
                    class="inline-flex items-center gap-1.5 text-subtle hover:text-brand transition"
                    style="font-size:var(--text-xs);"
                >
                    <x-icon name="globe" :size="14" />
                    {{ $labels[$current] ?? __('landing.footer.language') }}
                    <x-icon name="chevron-down" :size="12" />
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-transition
                    class="absolute right-0 bottom-full mb-2 bg-card border bd-subtle rounded-lg overflow-hidden"
                    style="z-index: var(--z-sticky); min-width:160px; box-shadow: var(--shadow-md);"
                >
                    @foreach ($locales as $loc)
                        <a
                            href="{{ route('locale.switch', $loc) }}"
                            class="block px-3 py-2 text-body hover:bg-sunken transition {{ $loc === $current ? 'text-brand font-semibold' : '' }}"
                            style="font-size:var(--text-sm);"
                        >{{ $labels[$loc] ?? $loc }}</a>
                    @endforeach
                </div>
            </span>
        </span>
    </div>
</footer>
