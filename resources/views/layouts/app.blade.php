<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ $title ?? config('app.name', 'Atendia') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/logo-mark-color.svg') }}" />

    {{-- Theme and sidebar rail before the first paint: avoids both flashes. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('atendia-theme');
                if (!t) {
                    t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.classList.toggle('dark', t === 'dark');

                if (localStorage.getItem('atendia-sidebar') === 'rail') {
                    document.documentElement.setAttribute('data-sidebar', 'rail');
                }
            } catch (e) {}

            // Pure DOM on purpose: collapsing is a CSS state on <html>, so no
            // Livewire render and no lost component state.
            window.atendiaSidebarToggle = function () {
                var el = document.documentElement;
                var rail = el.getAttribute('data-sidebar') !== 'rail';

                if (rail) {
                    el.setAttribute('data-sidebar', 'rail');
                } else {
                    el.removeAttribute('data-sidebar');
                }

                try {
                    localStorage.setItem('atendia-sidebar', rail ? 'rail' : 'open');
                } catch (e) {}
            };
        })();
    </script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    {{-- CSS and form-guard through Vite. app.js is NOT loaded, since it starts its
    own Alpine: Livewire brings Alpine and form-guard hooks onto it. --}}
    @vite(['resources/css/app.css', 'resources/js/form-guard.js', 'resources/js/dialog.js', 'resources/js/combobox.js', 'resources/js/file-field.js', 'resources/js/phone-field.js', 'resources/js/ws-phone.js', 'resources/js/catalog-master.js', 'resources/js/catalog-rail.js', 'resources/js/echo.js', 'resources/js/livewire-failures.js'])
    @livewireStyles
</head>
{{-- data-*: config for livewire-failures.js — JS can resolve neither routes
nor translations, and the graceful flag stays off with debug on (there the
Livewire overlay with the stack trace is the useful thing to see). --}}
<body
    data-login-url="{{ route('login', ['expired' => 1]) }}"
    data-fail-title="{{ __('errors.action_failed.title') }}"
    data-fail-message="{{ __('errors.action_failed.message') }}"
    @unless (config('app.debug')) data-graceful-failures @endunless
>
    <div class="app-shell" x-data="{ sidebarOpen: false }">
        {{-- Drawer scrim, mobile only. --}}
        <div
            class="sidebar-scrim"
            data-testid="sidebar-scrim"
            x-show="sidebarOpen"
            x-cloak
            @click="sidebarOpen = false"
        ></div>

        {{-- Sidebar --}}
        @php($onAdminPanel = request()->routeIs('admin.*'))

        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <a
                href="{{ $onAdminPanel ? route('admin.dashboard') : route('dashboard') }}"
                wire:navigate
                class="sidebar-header"
            >
                <img src="{{ asset('assets/logo-mark.svg') }}" alt="Atendia" class="sidebar-logo" />
                <span class="sidebar-wordmark">Atend<span>ia</span></span>
                @if ($onAdminPanel)
                    <x-ui.badge variant="accent">Admin</x-ui.badge>
                @endif
            </a>

            {{-- Desktop-only: collapses the menu to an icon rail. The drawer
            owns mobile, where this button never shows. CSS picks the chevrons
            for the current state: << to collapse, >> to expand. --}}
            <button
                type="button"
                class="sidebar-collapse"
                data-testid="sidebar-collapse"
                aria-label="{{ __('menu.sidebar_toggle') }}"
                @click="window.atendiaSidebarToggle()"
            >
                <x-icon name="chevrons-left" :size="11" class="sidebar-collapse-in" />
                <x-icon name="chevrons-right" :size="11" class="sidebar-collapse-out" />
            </button>

            <livewire:navigation />
        </aside>

        {{-- Columna principal --}}
        <div class="app-main">
            <header class="topbar">
                <button
                    type="button"
                    class="icon-btn icon-btn-secondary topbar-burger"
                    data-testid="sidebar-toggle"
                    @click="sidebarOpen = true"
                    aria-label="Abrir menú"
                >
                    <x-icon name="menu" :size="20" />
                </button>

                <div class="topbar-search">
                    <x-ui.input
                        name="search"
                        type="search"
                        icon="search"
                        placeholder="Buscar conversación, turno, producto…"
                        autocomplete="off"
                    />
                </div>

                <div class="topbar-actions">
                    <span class="conn-pill"> <span class="conn-dot"></span>WhatsApp conectado </span>

                    <x-ui.theme-toggle />

                    <button type="button" class="icon-btn icon-btn-secondary topbar-notif" aria-label="Notificaciones">
                        <x-icon name="bell" :size="20" />
                        <span class="topbar-notif-dot"></span>
                    </button>

                    <div class="topbar-user-menu" x-data="{ open: false }">
                        <button
                            type="button"
                            class="topbar-user"
                            data-testid="user-menu"
                            @click="open = ! open"
                            :aria-expanded="open"
                        >
                            <x-ui.avatar
                                :name="auth()->user()?->name ?? 'Atendia'"
                                size="sm"
                                status="online"
                                tint="brand"
                            />
                            <div class="topbar-user-meta">
                                <span class="topbar-user-org">{{ auth()->user()?->name }}</span>
                                <span class="topbar-user-name">{{ auth()->user()?->email }}</span>
                            </div>
                            <x-icon name="chevron-down" :size="16" class="topbar-user-caret" />
                        </button>

                        <div
                            class="topbar-user-dropdown"
                            x-show="open"
                            x-cloak
                            x-transition
                            @click.outside="open = false"
                        >
                            {{-- Panel switch, admin only. Impersonating one particular
                            customer is a separate feature for later; this
                            only changes panel. --}}
                            @can('access-admin-panel')
                                @if ($onAdminPanel)
                                    <a href="{{ route('dashboard') }}" wire:navigate class="dropdown-item">
                                        <x-icon name="store" :size="16" /> Ver panel cliente
                                    </a>
                                @else
                                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="dropdown-item">
                                        <x-icon name="shield-check" :size="16" /> Panel admin
                                    </a>
                                @endif
                            @endcan

                            <a href="{{ route('profile.edit') }}" wire:navigate class="dropdown-item">
                                <x-icon name="settings" :size="16" /> {{ __('menu.settings') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item dropdown-item-danger">
                                    <x-icon name="log-out" :size="16" /> Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            @isset($header)
                <div class="app-page-header">{{ $header }}</div>
            @endisset

            {{-- The shell stays still: only this region animates between SPA visits. --}}
            <main class="app-content" wire:transition.navigate>{{ $slot }}</main>
        </div>
    </div>

    {{-- The toast stack: one instance for the whole app, fed by any component
    through the notification trait. --}}
    <livewire:toast />

    {{-- The system's dialog window: mounted ONCE and used by any component
    through `dialog.*`. AtendIa has no native browser alerts — see
    .ai/guidelines/avisos-y-modales.md. --}}
    <livewire:dialog />

    @livewireScripts
</body>
</html>
