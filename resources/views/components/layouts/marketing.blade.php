<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    {{-- The brand is read from the Company row, never spelled out: it is about
    to be renamed and a page title is the first place that would lie. --}}
    @php($brand = \App\Models\Company::brand())
    @php($headline = $title ?? $brand.' — '.__('landing.tagline'))

    <meta name="description" content="{{ __('landing.meta_description') }}" />

    <title>{{ $headline }}</title>

    {{-- Shared over WhatsApp — our own channel — the link must unfurl with a card. --}}
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="{{ $brand }}" />
    <meta property="og:title" content="{{ $headline }}" />
    <meta property="og:description" content="{{ __('landing.meta_description') }}" />
    <meta property="og:image" content="{{ asset('assets/og.png') }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ asset('assets/og.png') }}" />

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

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/hero.js'])
</head>
<body>
    {{ $slot }}
</body>
</html>
