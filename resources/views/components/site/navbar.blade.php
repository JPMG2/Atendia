@php
    // Menu order IS the page order: WelcomePageTest fails if they diverge.
    $links = [
        ['label' => __('landing.nav.how'), 'id' => 'como-funciona'],
        ['label' => __('landing.nav.features'), 'id' => 'funciones'],
        ['label' => __('landing.nav.cases'), 'id' => 'casos'],
        ['label' => __('landing.nav.pricing'), 'id' => 'precios'],
        ['label' => __('landing.nav.faq'), 'id' => 'preguntas'],
    ];
@endphp

<header
    x-data="{
        dark: document.documentElement.classList.contains('dark'),
        open: false,
        active: '',
        progress: 0,
        scrolled: false,
        pastPricing: false,
        sections: @js(array_column($links, 'id')),
        track() {
            // The section whose top already passed under the bar is the one being read.
            const line = window.scrollY + 96;
            this.active = this.sections.reduce((current, id) => {
                const el = document.getElementById(id);

                return el && el.getBoundingClientRect().top + window.scrollY <= line ? id : current;
            }, '');

            const travel = document.documentElement.scrollHeight - window.innerHeight;
            this.progress = travel > 0 ? Math.min(1, window.scrollY / travel) : 0;

            this.scrolled = window.scrollY > 24;

            // The accent shows up once per view, and this is the moment: the
            // price is already on the table.
            this.pastPricing = this.sections.indexOf(this.active) >= this.sections.indexOf('precios');
        },
        toggleTheme() {
            this.dark = ! this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try {
                localStorage.setItem('atendia-theme', this.dark ? 'dark' : 'light');
            } catch (e) {}
        },
    }"
    x-init="track()"
    @scroll.window.passive="track()"
    @resize.window.passive="track()"
    x-bind:class="scrolled && 'navbar-compact'"
    class="navbar-frosted sticky top-0 flex w-full justify-center"
    style="z-index: var(--z-sticky)"
>
    <div class="nav-progress" aria-hidden="true" x-bind:style="`transform: scaleX(${progress})`"></div>
    <div class="navbar-row flex w-full items-center gap-6 px-6" style="max-width: var(--container-xl)">
        <x-site.logo :size="24" class="navbar-logo" />

        <nav class="ml-2 hidden gap-1 md:flex">
            @foreach ($links as $link)
                <a
                    href="#{{ $link['id'] }}"
                    x-bind:class="active === '{{ $link['id'] }}' && 'nav-link-active'"
                    x-bind:aria-current="active === '{{ $link['id'] }}' ? 'true' : null"
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
                <x-ui.button variant="ghost" size="sm" :href="Route::has('login') ? route('login') : '#'">
                    {{ __('landing.nav.login') }}</x-ui.button>
                <x-ui.button
                    variant="primary"
                    size="sm"
                    x-bind:class="pastPricing && 'btn-accent'"
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
                    href="#{{ $link['id'] }}"
                    @click="open = false"
                    x-bind:class="active === '{{ $link['id'] }}' && 'nav-link-active'"
                    x-bind:aria-current="active === '{{ $link['id'] }}' ? 'true' : null"
                    class="text-body hover:bg-sunken rounded-lg px-3 py-2.5 font-semibold transition"
                >{{ $link['label'] }}</a>
            @endforeach
            <div class="mt-4 flex flex-col gap-2.5">
                <x-ui.button variant="secondary" size="md" fullWidth :href="Route::has('login') ? route('login') : '#'">
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
