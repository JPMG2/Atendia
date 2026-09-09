@php
    $links = [
        ['label' => __('landing.nav.how'), 'href' => '#como-funciona'],
        ['label' => __('landing.nav.features'), 'href' => '#funciones'],
        ['label' => __('landing.nav.cases'), 'href' => '#casos'],
        ['label' => __('landing.nav.pricing'), 'href' => '#precios'],
    ];
@endphp

<header
    x-data="{
        dark: document.documentElement.classList.contains('dark'),
        open: false,
        toggleTheme() {
            this.dark = ! this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try {
                localStorage.setItem('atendia-theme', this.dark ? 'dark' : 'light');
            } catch (e) {}
        },
    }"
    class="navbar-frosted sticky top-0 flex w-full justify-center"
    style="z-index: var(--z-sticky)"
>
    <div class="flex w-full items-center gap-6 px-6 py-3" style="max-width: var(--container-xl)">
        <x-site.logo :size="24" />

        <nav class="ml-2 hidden gap-1 md:flex">
            @foreach ($links as $link)
                <a
                    href="{{ $link['href'] }}"
                    class="text-body hover:bg-sunken rounded-lg px-3 py-2 text-sm font-semibold transition"
                >{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2.5">
            {{-- Theme toggle: the slot holds the two icons Alpine swaps. --}}
            <x-ui.icon-button :label="__('landing.nav.toggle_theme')" @click="toggleTheme()">
                <span x-show="! dark"><x-icon name="moon" :size="18" /></span>
                <span x-show="dark" x-cloak><x-icon name="sun" :size="18" /></span>
            </x-ui.icon-button>

            <div class="hidden gap-2.5 sm:flex">
                <x-ui.button
                    variant="ghost"
                    size="sm"
                    :href="Route::has('login') ? route('login') : '#'"
                >
                    {{ __('landing.nav.login') }}</x-ui.button>
                <x-ui.button
                    variant="primary"
                    size="sm"
                    :href="Route::has('register') ? route('register') : '#'"
                >
                    {{ __('landing.nav.register') }}</x-ui.button>
            </div>

            {{-- Hamburguesa (mobile) --}}
            <x-ui.icon-button icon="menu" :label="__('landing.nav.open_menu')" class="md:hidden" @click="open = true" />
        </div>
    </div>

    {{-- Mobile drawer. --}}
    <div
        x-show="open"
        x-cloak
        @keydown.escape.window="open = false"
        class="fixed inset-0 md:hidden"
        style="z-index: var(--z-overlay)"
    >
        <div class="absolute inset-0" style="background: var(--surface-overlay)" @click="open = false"></div>
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            class="bg-card bd-subtle absolute right-0 top-0 flex h-full w-72 flex-col gap-2 border-l p-6"
        >
            <div class="mb-4 flex items-center justify-between">
                <x-site.logo :size="22" />
                <x-ui.icon-button
                    icon="x"
                    size="sm"
                    variant="ghost"
                    :label="__('landing.nav.close_menu')"
                    @click="open = false"
                />
            </div>
            @foreach ($links as $link)
                <a
                    href="{{ $link['href'] }}"
                    @click="open = false"
                    class="text-body hover:bg-sunken rounded-lg px-3 py-2.5 font-semibold transition"
                >{{ $link['label'] }}</a>
            @endforeach
            <div class="mt-4 flex flex-col gap-2.5">
                <x-ui.button
                    variant="secondary"
                    size="md"
                    fullWidth
                    :href="Route::has('login') ? route('login') : '#'"
                >
                    {{ __('landing.nav.login') }}</x-ui.button>
                <x-ui.button
                    variant="primary"
                    size="md"
                    fullWidth
                    :href="Route::has('register') ? route('register') : '#'"
                >
                    {{ __('landing.nav.register') }}</x-ui.button>
            </div>
        </div>
    </div>
</header>
