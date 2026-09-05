<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- The default comes from translations: a PHP attribute cannot call __(). --}}
    <title>{{ $title ?? __('wizard.title') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/logo-mark-color.svg') }}">

    {{-- Theme before the first paint: it avoids the light-to-dark flash. --}}
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

    {{-- No app.js: Livewire brings its own Alpine and form-guard hooks onto it. --}}
    @vite(['resources/css/app.css', 'resources/js/form-guard.js', 'resources/js/dialog.js', 'resources/js/combobox.js', 'resources/js/file-field.js', 'resources/js/phone-field.js'])
    @livewireStyles
</head>
<body class="bg-page">
    {{ $slot }}

    <livewire:toast />
    <livewire:dialog />
    @livewireScripts
</body>
</html>
