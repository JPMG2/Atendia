<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ $title ?? 'Atendia — Tu negocio, atendido por IA' }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/logo-mark-color.svg') }}" />

    {{-- Theme before the first paint: it avoids the light-to-dark flash. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('atendia-theme');
                if (!t) {
                    t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.classList.toggle('dark', t === 'dark');
            } catch (e) {}
        })();
    </script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    {{-- form-guard has no Alpine of its own (it hangs validate() on window),
    so it rides along app.js safely on the guest pages. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/form-guard.js'])
</head>
<body
    x-data="{
        dark: document.documentElement.classList.contains('dark'),
        toggleTheme() {
            this.dark = ! this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try {
                localStorage.setItem('atendia-theme', this.dark ? 'dark' : 'light');
            } catch (e) {}
        },
    }"
    class="bg-page grid min-h-screen lg:grid-cols-2"
>
    {{-- Brand panel, desktop only. --}}
    <aside
        class="relative hidden flex-col justify-between overflow-hidden p-12 lg:flex xl:p-16"
        style="
            background:
                radial-gradient(
                    120% 90% at 18% 12%,
                    color-mix(in srgb, var(--brand) 20%, transparent),
                    transparent 55%
                ),
                var(--surface-sunken);
        "
    >
        <x-site.logo :size="26" href="{{ url('/') }}" />

        <div class="max-w-md">
            <p class="eyebrow eyebrow-line mb-5">Tu negocio, atendido por IA</p>
            <h1
                class="text-strong font-display"
                style="font-weight: 800; font-size: var(--text-4xl); line-height: 1.1; letter-spacing: -0.02em"
            >
                Automatizá tu WhatsApp y no pierdas más clientes.
            </h1>
            <p class="text-body mt-5" style="font-size: var(--text-lg); line-height: 1.6">
                Tu asistente responde, agenda turnos y muestra tus productos. Día y noche, en segundos.
            </p>

            <ul class="mt-9 flex flex-col gap-4">
                @foreach ([
                    ['icon' => 'message-circle', 'text' => 'Responde en 2 segundos, las 24 horas'],
                    ['icon' => 'calendar-check', 'text' => 'Agenda turnos sin que muevas un dedo'],
                    ['icon' => 'store', 'text' => 'Muestra tu catálogo dentro del chat'],
                ] as $point)
                    <li class="flex items-center gap-3.5">
                        <span
                            class="inline-flex shrink-0 items-center justify-center"
                            style="
                                width: 38px;
                                height: 38px;
                                border-radius: var(--radius-md);
                                background: var(--brand-soft);
                                color: var(--brand-soft-text);
                            "
                        >
                            <x-icon :name="$point['icon']" :size="20" />
                        </span>
                        <span class="text-body" style="font-size: var(--text-base)">{{ $point['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="text-subtle" style="font-size: var(--text-sm)">
            © {{ date('Y') }} Atendia · Hecho para que atiendas mejor.
        </p>
    </aside>

    {{-- Form panel. --}}
    <main class="relative flex flex-col items-center justify-center px-5 py-12 sm:px-8">
        {{-- Theme toggle. --}}
        <div class="absolute right-5 top-5">
            <x-ui.icon-button label="Cambiar tema" @click="toggleTheme()">
                <span x-show="! dark"><x-icon name="moon" :size="18" /></span>
                <span x-show="dark" x-cloak><x-icon name="sun" :size="18" /></span>
            </x-ui.icon-button>
        </div>

        {{-- Compact logo: mobile only, where the brand panel is hidden. --}}
        <x-site.logo :size="26" :href="url('/')" class="mb-8 lg:hidden" />

        <div class="w-full" style="max-width: 26rem">{{ $slot }}</div>
    </main>
</body>
</html>
