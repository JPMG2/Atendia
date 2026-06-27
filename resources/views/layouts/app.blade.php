<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Atendia') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/logo-mark-color.svg') }}">

    {{-- Tema antes del primer pintado: evita el flash claro→oscuro --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('atendia-theme');
                if (!t) { t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
                document.documentElement.classList.toggle('dark', t === 'dark');
            } catch (e) {}
        })();
    </script>

    <style>[x-cloak]{display:none !important;}</style>

    {{-- Solo CSS por Vite: Livewire trae su propio Alpine (nada de app.js → cero doble Alpine) --}}
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body>
    <div class="app-shell" x-data="{ sidebarOpen: false }">

        {{-- Scrim del drawer (solo mobile) --}}
        <div class="sidebar-scrim" data-testid="sidebar-scrim" x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <a href="{{ route('dashboard') }}" wire:navigate class="sidebar-header">
                <img src="{{ asset('assets/logo-mark.svg') }}" alt="Atendia" class="sidebar-logo">
                <span class="sidebar-wordmark">Atend<span>ia</span></span>
            </a>

            <livewire:navigation />
        </aside>

        {{-- Columna principal --}}
        <div class="app-main">
            <header class="topbar">
                <button type="button" class="icon-btn icon-btn-secondary topbar-burger"
                        data-testid="sidebar-toggle" @click="sidebarOpen = true" aria-label="Abrir menú">
                    <x-icon name="menu" :size="20" />
                </button>

                <div class="topbar-search">
                    <x-ui.input name="search" type="search" icon="search"
                                placeholder="Buscar conversación, turno, producto…" autocomplete="off" />
                </div>

                <div class="topbar-actions">
                    <span class="conn-pill">
                        <span class="conn-dot"></span>WhatsApp conectado
                    </span>

                    <button type="button" class="icon-btn icon-btn-secondary" data-testid="theme-toggle"
                            x-data="{ dark: document.documentElement.classList.contains('dark') }"
                            @click="dark = ! dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('atendia-theme', dark ? 'dark' : 'light')"
                            :aria-label="dark ? 'Activar tema claro' : 'Activar tema oscuro'">
                        <x-icon name="sun" :size="20" x-show="dark" x-cloak />
                        <x-icon name="moon" :size="20" x-show="! dark" x-cloak />
                    </button>

                    <button type="button" class="icon-btn icon-btn-secondary topbar-notif" aria-label="Notificaciones">
                        <x-icon name="bell" :size="20" />
                        <span class="topbar-notif-dot"></span>
                    </button>

                    <div class="topbar-user-menu" x-data="{ open: false }">
                        <button type="button" class="topbar-user" data-testid="user-menu"
                                @click="open = ! open" :aria-expanded="open">
                            <x-ui.avatar :name="auth()->user()?->name ?? 'Atendia'" size="sm" status="online" tint="brand" />
                            <div class="topbar-user-meta">
                                <span class="topbar-user-org">{{ auth()->user()?->name }}</span>
                                <span class="topbar-user-name">{{ auth()->user()?->email }}</span>
                            </div>
                            <x-icon name="chevron-down" :size="16" class="topbar-user-caret" />
                        </button>

                        <div class="topbar-user-dropdown" x-show="open" x-cloak x-transition
                             @click.outside="open = false">
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

            <main class="app-content">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
