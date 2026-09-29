<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    {{-- A booking page has nothing to rank for: it belongs to whoever has the link. --}}
    <meta name="robots" content="noindex" />

    <title>{{ $title ?? __('agenda.public.title') }}</title>

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

    {{-- Its own layout and not the guest one: the date and service controls
    need their Alpine modules, and the login pages must not carry them. No
    app.js here — these modules ride the Alpine that Livewire brings, and a
    second Alpine start throws "Illegal invocation". --}}
    @vite(['resources/css/app.css', 'resources/js/combobox.js', 'resources/js/datepicker.js', 'resources/js/form-guard.js'])
</head>
<body class="bg-page min-h-screen">
    {{ $slot }}

    <p class="text-subtle pb-8 text-center text-xs">{{ __('agenda.public.powered') }}</p>
</body>
</html>
