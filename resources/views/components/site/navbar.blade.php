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
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try { localStorage.setItem('atendia-theme', this.dark ? 'dark' : 'light'); } catch (e) {}
        }
    }"
    class="navbar-frosted sticky top-0 w-full flex justify-center"
    style="z-index: var(--z-sticky);"
>
    <div class="w-full flex items-center gap-6 px-6 py-3" style="max-width: var(--container-xl);">
        <x-site.logo :size="24" />

        <nav class="hidden md:flex gap-1 ml-2">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="text-body px-3 py-2 rounded-lg text-sm font-semibold hover:bg-sunken transition">{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2.5">
            {{-- Toggle de tema (slot con los dos iconos que Alpine alterna) --}}
            <x-ui.icon-button :label="__('landing.nav.toggle_theme')" @click="toggleTheme()">
                <span x-show="!dark"><x-icon name="moon" :size="18" /></span>
                <span x-show="dark" x-cloak><x-icon name="sun" :size="18" /></span>
            </x-ui.icon-button>

            <div class="hidden sm:flex gap-2.5">
                <x-ui.button variant="ghost" size="sm" :href="Route::has('login') ? route('login') : '#'">{{ __('landing.nav.login') }}</x-ui.button>
                <x-ui.button variant="primary" size="sm" :href="Route::has('register') ? route('register') : '#'">{{ __('landing.nav.register') }}</x-ui.button>
            </div>

            {{-- Hamburguesa (mobile) --}}
            <x-ui.icon-button icon="menu" :label="__('landing.nav.open_menu')" class="md:hidden" @click="open = true" />
        </div>
    </div>

    {{-- Drawer móvil --}}
    <div
        x-show="open"
        x-cloak
        @keydown.escape.window="open = false"
        class="fixed inset-0 md:hidden"
        style="z-index: var(--z-overlay);"
    >
        <div class="absolute inset-0" style="background: var(--surface-overlay);" @click="open = false"></div>
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            class="absolute top-0 right-0 h-full w-72 bg-card border-l bd-subtle p-6 flex flex-col gap-2"
        >
            <div class="flex items-center justify-between mb-4">
                <x-site.logo :size="22" />
                <x-ui.icon-button icon="x" size="sm" variant="ghost" :label="__('landing.nav.close_menu')" @click="open = false" />
            </div>
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" @click="open = false" class="text-body px-3 py-2.5 rounded-lg font-semibold hover:bg-sunken transition">{{ $link['label'] }}</a>
            @endforeach
            <div class="flex flex-col gap-2.5 mt-4">
                <x-ui.button variant="secondary" size="md" fullWidth :href="Route::has('login') ? route('login') : '#'">{{ __('landing.nav.login') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" fullWidth :href="Route::has('register') ? route('register') : '#'">{{ __('landing.nav.register') }}</x-ui.button>
            </div>
        </div>
    </div>
</header>
