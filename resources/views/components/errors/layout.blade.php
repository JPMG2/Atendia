@props(['code', 'title', 'message'])

{{-- Standalone by design: an error page cannot depend on the app that just
failed. No Livewire, no Alpine, no session and no database — only the
compiled CSS tokens and static assets. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ $title }} — Atendia</title>

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

    @vite('resources/css/app.css')
</head>
<body class="bg-page flex min-h-screen flex-col">
    <header class="flex justify-center pt-10 sm:pt-14">
        {{-- The bundled mark, not <x-site.logo>: that component reads the
        company from the database and this page must survive without it. --}}
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5">
            <img src="{{ asset('assets/logo-mark.svg') }}" alt="" style="width: 32px; height: 32px" />
            <span
                class="font-display"
                style="font-weight: 800; font-size: 24px; letter-spacing: -0.03em; color: var(--text-strong)"
            >Atend<span class="text-brand">ia</span></span>
        </a>
    </header>

    <main class="relative flex flex-1 flex-col items-center justify-center overflow-hidden px-5 py-12">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0"
            style="
                background: radial-gradient(
                    60% 45% at 50% 42%,
                    color-mix(in srgb, var(--brand) 12%, transparent),
                    transparent 70%
                );
            "
        ></div>

        <div class="relative w-full" style="max-width: 32rem">
            {{-- The incoming chat bubble: the phone "says" the error, full-size
            and readable, never squeezed inside the phone's screen. --}}
            <div
                class="relative text-center"
                style="
                    background: var(--bubble-in);
                    color: var(--bubble-in-text);
                    border: 1px solid var(--border-subtle);
                    border-radius: 20px 20px 20px 6px;
                    box-shadow: var(--shadow-lg);
                    padding: 32px 28px;
                "
            >
                <p class="text-brand font-mono" style="font-size: var(--text-6xl); font-weight: 700; line-height: 1">
                    {{ $code }}
                </p>

                <h1
                    class="text-strong mt-4 font-display"
                    style="font-weight: 800; font-size: var(--text-3xl); letter-spacing: -0.02em"
                >
                    {{ $title }}
                </h1>

                <p class="text-body mt-3" style="font-size: var(--text-base); line-height: 1.6">{{ $message }}</p>

                {{-- The bubble tail, pointing at the phone below. --}}
                <svg aria-hidden="true" viewBox="0 0 24 18" style="position:absolute; left:22px; bottom:-15px; width:24px; height:18px; fill:var(--bubble-in);"><path d="M2 0h22C18 10 10 16 0 18 2 12 3 6 2 0Z" /></svg>
            </div>

            {{-- The sender: a small decorative phone, tilted like it just spoke. --}}
            <div class="mt-6 flex items-end gap-3" style="padding-left: 8px">
                <svg aria-hidden="true" viewBox="0 0 64 118" style="
                        width: 56px;
                        height: 103px;
                        transform: rotate(-8deg);
                    ">
                    <rect x="2" y="2" width="60" height="114" rx="14" style="fill:var(--surface-card); stroke:var(--border-default); stroke-width:3;" />
                    <rect x="10" y="16" width="44" height="86" rx="8" style="fill:var(--chat-canvas);" />
                    <rect x="24" y="7" width="16" height="3.5" rx="1.75" style="fill:var(--border-default);" />
                    <rect x="15" y="24" width="24" height="10" rx="5" style="fill:var(--bubble-out);" />
                    <rect x="25" y="40" width="24" height="10" rx="5" style="fill:var(--surface-card);" />
                    <rect x="15" y="56" width="20" height="10" rx="5" style="fill:var(--bubble-out);" />
                    <circle cx="32" cy="109" r="3" style="fill:var(--border-default);" />
                </svg>

                @if (! $slot->isEmpty())
                    <div class="flex flex-wrap gap-3 pb-1">{{ $slot }}</div>
                @endif
            </div>
        </div>
    </main>

    <footer class="px-5 pb-8 text-center">
        <p class="text-subtle" style="font-size: var(--text-sm)">
            © {{ date('Y') }} Atendia · Tu negocio, atendido por IA.
        </p>
    </footer>
</body>
</html>
